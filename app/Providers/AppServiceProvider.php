<?php

namespace App\Providers;

use App\Payments\MockPaymentProvider;
use App\Payments\PaymentProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentProvider::class, function () {
            $provider = new \App\Payments\MockPaymentProvider;
            $provider->forcedOutcome = config('services.mock_payment.outcome') ?: null;

            return $provider;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
