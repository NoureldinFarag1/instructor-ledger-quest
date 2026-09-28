<?php

namespace App\Console\Commands;

use App\Ledger\CreatePayouts;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class PayoutCommand extends Command
{
    protected $signature = 'ledger:payout {--date= : Payout run date (default today)}';

    protected $description = 'Reserve outstanding balances and pay instructors';

    public function handle(CreatePayouts $create): int
    {
        $date = CarbonImmutable::parse($this->option('date') ?? 'today');
        $count = $create->handle($date);

        $this->info("Created {$count} payout(s) for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
