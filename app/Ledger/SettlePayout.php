<?php

namespace App\Ledger;

use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Payments\ProviderResult;
use Illuminate\Support\Facades\DB;

class SettlePayout
{
    public function handle(int $payoutId, ProviderResult $result): void
    {
        DB::transaction(function () use ($payoutId, $result) {
            $payout = Payout::whereKey($payoutId)->lockForUpdate()->firstOrFail();

            // Only in-flight payouts can be settled; anything else is already final.
            if (! in_array($payout->status, [Payout::STATUS_PROCESSING, Payout::STATUS_UNKNOWN], true)) {
                return;
            }

            if ($result->status === ProviderResult::SUCCEEDED) {
                $payout->update([
                    'status' => Payout::STATUS_SUCCEEDED,
                    'provider_reference' => $result->reference,
                    'last_error' => null,
                ]);

                return;
            }

            // Failed, or the provider never received it: money didn't move, so release it.
            $payout->update([
                'status' => Payout::STATUS_FAILED,
                'last_error' => $result->error ?? 'Not found at provider',
            ]);

            LedgerEntry::create([
                'instructor_id' => $payout->instructor_id,
                'payout_id' => $payout->id,
                'type' => LedgerEntry::TYPE_PAYOUT_RELEASED,
                'amount' => $payout->amount,
            ]);
        });
    }
}
