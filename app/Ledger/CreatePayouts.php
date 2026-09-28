<?php

namespace App\Ledger;

use App\Jobs\ProcessPayout;
use App\Models\Instructor;
use App\Models\LedgerEntry;
use App\Models\Payout;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreatePayouts
{
    public function handle(CarbonImmutable $date): int
    {
        $created = 0;

        $instructorIds = LedgerEntry::query()
            ->select('instructor_id')
            ->groupBy('instructor_id')
            ->havingRaw('SUM(amount) > 0')
            ->pluck('instructor_id');

        foreach ($instructorIds as $instructorId) {
            $payoutId = DB::transaction(function () use ($instructorId, $date) {
                // One payout creation per instructor at a time, across all servers.
                Instructor::whereKey($instructorId)->lockForUpdate()->firstOrFail();

                $key = "payout:{$instructorId}:{$date->toDateString()}";
                if (Payout::where('idempotency_key', $key)->exists()) {
                    return null; // this run already happened
                }

                $balance = (int) LedgerEntry::where('instructor_id', $instructorId)->sum('amount');
                if ($balance <= 0) {
                    return null;
                }

                $payout = Payout::create([
                    'instructor_id' => $instructorId,
                    'amount' => $balance,
                    'idempotency_key' => $key,
                    'status' => Payout::STATUS_PENDING,
                ]);

                LedgerEntry::create([
                    'instructor_id' => $instructorId,
                    'payout_id' => $payout->id,
                    'type' => LedgerEntry::TYPE_PAYOUT_RESERVED,
                    'amount' => -$balance,
                ]);

                return $payout->id;
            });

            // Dispatched only after the transaction committed.
            if ($payoutId) {
                ProcessPayout::dispatch($payoutId);
                $created++;
            }
        }

        return $created;
    }
}
