<?php

namespace App\Payments;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MockPaymentProvider implements PaymentProvider
{
    /** Force an outcome in tests: 'success', 'fail' or 'timeout'. Null means random. */
    public ?string $forcedOutcome = null;

    public function send(string $key, int $instructorId, int $amount): ProviderResult
    {
        $outcome = $this->forcedOutcome ?? $this->randomOutcome();

        if ($outcome === 'fail') {
            $this->record($key, ProviderResult::FAILED);

            return new ProviderResult(ProviderResult::FAILED, error: 'Declined by provider');
        }

        // Money moves here, for both 'success' and 'timeout'.
        $reference = 'txn_'.Str::random(12);
        $this->record($key, ProviderResult::SUCCEEDED, $reference);
        Cache::increment("mockpay:transfers:{$key}");

        if ($outcome === 'timeout') {
            throw new ProviderTimeoutException('Provider timed out after moving the money.');
        }

        return new ProviderResult(ProviderResult::SUCCEEDED, $reference);
    }

    public function status(string $key): ProviderResult
    {
        $stored = Cache::get("mockpay:result:{$key}");

        return $stored
            ? new ProviderResult($stored['status'], $stored['reference'])
            : new ProviderResult(ProviderResult::NOT_FOUND);
    }

    /** Test helper: how many times money actually moved for this key. */
    public function transfers(string $key): int
    {
        return (int) Cache::get("mockpay:transfers:{$key}", 0);
    }

    private function record(string $key, string $status, ?string $reference = null): void
    {
        Cache::forever("mockpay:result:{$key}", ['status' => $status, 'reference' => $reference]);
    }

    private function randomOutcome(): string
    {
        $roll = random_int(1, 100);

        return match (true) {
            $roll <= 70 => 'success',
            $roll <= 85 => 'fail',
            default => 'timeout',
        };
    }
}
