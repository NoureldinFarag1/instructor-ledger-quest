<?php

namespace App\Jobs;

use App\Ledger\SettlePayout;
use App\Models\Payout;
use App\Payments\PaymentProvider;
use App\Payments\ProviderTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessPayout implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $payoutId) {}

    public function handle(PaymentProvider $provider, SettlePayout $settle): void
    {
        // Claim: only a pending payout may be sent, and only once.
        $payout = DB::transaction(function () {
            $p = Payout::whereKey($this->payoutId)->lockForUpdate()->firstOrFail();

            if ($p->status !== Payout::STATUS_PENDING) {
                return null; // retried, duplicated, or already handled
            }

            $p->update(['status' => Payout::STATUS_PROCESSING, 'attempts' => $p->attempts + 1]);

            return $p;
        });

        if (! $payout) {
            return;
        }

        try {
            $result = $provider->send($payout->idempotency_key, $payout->instructor_id, $payout->amount);
        } catch (ProviderTimeoutException $e) {
            // We don't know if money moved. Never resend; reconcile will ask the provider.
            Payout::whereKey($payout->id)
                ->where('status', Payout::STATUS_PROCESSING)
                ->update(['status' => Payout::STATUS_UNKNOWN, 'last_error' => $e->getMessage()]);

            return;
        }

        $settle->handle($payout->id, $result);
    }
}
