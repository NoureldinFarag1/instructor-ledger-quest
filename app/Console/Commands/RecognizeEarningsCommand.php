<?php

namespace App\Console\Commands;

use App\Ledger\RecognizeEarnings;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RecognizeEarningsCommand extends Command
{
    protected $signature = 'ledger:recognize {--date= : Recognize as of this date (default today)}';

    protected $description = 'Recognize instructor earnings up to a date';

    public function handle(RecognizeEarnings $recognize): int
    {
        $asOf = CarbonImmutable::parse($this->option('date') ?? 'today');

        Subscription::where('starts_on', '<=', $asOf)
            ->chunkById(500, function ($subs) use ($recognize, $asOf) {
                foreach ($subs as $sub) {
                    $recognize->handle($sub, $asOf);
                }
            });

        $this->info("Recognized earnings through {$asOf->toDateString()}.");

        return self::SUCCESS;
    }
}
