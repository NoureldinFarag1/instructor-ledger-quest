<?php

namespace App\Ledger;

use App\Models\LedgerEntry;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RecognizeEarnings
{
    public function __construct(private EarningsCalculator $calculator) {}

    public function handle(Subscription $subscription, CarbonImmutable $asOf): void
    {
        DB::transaction(function () use ($subscription, $asOf) {
            // Lock the row for concurrency
            $sub = Subscription::whereKey($subscription->id)
                ->lockForUpdate()
                ->with('instructors')
                ->firstOrFail();

            $lastDay = ($sub->refunded_on && $sub->refunded_on->lt($asOf) ? $sub->refunded_on : $asOf);

            $termDays = (int) $sub->starts_on->diffInDays($sub->ends_on);
            $elapsed = (int) $sub->starts_on->diffInDays($lastDay) + 1;

            $pool = $this->calculator->instructorPool($sub->amount, $sub->instructor_share_bps);
            $earnedPool = $this->calculator->earnedToDate($pool, $elapsed, $termDays);

            $targets = $this->calculator->split(
                $earnedPool,
                $sub->instructors->pluck('pivot.weight', 'id')->all()
            );

            $already = LedgerEntry::query()
                ->where('subscription_id', $sub->id)
                ->where('type', LedgerEntry::TYPE_EARNED)
                ->groupBy('instructor_id')
                ->selectRaw('instructor_id, sum(amount) AS Total')
                ->pluck('Total', 'instructor_id');

            foreach ($targets as $instructorId => $target) {
                $delta = $target - (int) ($already[$instructorId] ?? 0);

                if ($delta > 0) {
                    LedgerEntry::create([
                        'instructor_id' => $instructorId,
                        'subscription_id' => $sub->id,
                        'type' => LedgerEntry::TYPE_EARNED,
                        'amount' => $delta,
                        'recognized_through' => $lastDay->toDateString(),
                    ]);
                }
            }
        });
    }
}
