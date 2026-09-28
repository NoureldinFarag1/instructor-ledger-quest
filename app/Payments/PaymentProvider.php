<?php

namespace App\Payments;

interface PaymentProvider
{
    /** @throws ProviderTimeoutException when we can't tell whether money moved*/
    public function send(string $key, int $instructorId, int $amount): ProviderResult;

    public function status(string $key): ProviderResult;
}
