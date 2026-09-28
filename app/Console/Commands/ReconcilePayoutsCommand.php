<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPayout;
use App\Ledger\SettlePayout;
use App\Models\Payout;
use App\Payments\PaymentProvider;
use Illuminate\Console\Command;

class ReconcilePayoutsCommand extends Command
{
    protected $signature = 'ledger:reconcile {--stale=10 : Minutes before a stuck payout is checked}';

    protected $description = 'Resolve unknown or stuck payouts by asking the provider';

    public function handle(PaymentProvider $provider, SettlePayout $settle): int
    {
        $cutoff = now()->subMinutes((int) $this->option('stale'));

        // Unknown outcomes, and payouts whose worker died mid-call.
        Payout::query()
            ->where('status', Payout::STATUS_UNKNOWN)
            ->orWhere(fn ($q) => $q->where('status', Payout::STATUS_PROCESSING)
                ->where('updated_at', '<', $cutoff))
            ->each(fn ($p) => $settle->handle($p->id, $provider->status($p->idempotency_key)));

        // Pending payouts whose job was lost before it ran.
        Payout::query()
            ->where('status', Payout::STATUS_PENDING)
            ->where('created_at', '<', $cutoff)
            ->each(fn ($p) => ProcessPayout::dispatch($p->id));

        $this->info('Reconciliation complete.');

        return self::SUCCESS;
    }
}
