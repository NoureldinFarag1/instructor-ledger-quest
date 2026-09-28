<?php

use App\Jobs\ProcessPayout;
use App\Models\Instructor;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\Subscription;
use App\Payments\PaymentProvider;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->provider = app(PaymentProvider::class);
    $this->instructor = Instructor::factory()->create();

    LedgerEntry::create([
        'instructor_id' => $this->instructor->id,
        'subscription_id' => Subscription::factory()->create()->id,
        'type' => LedgerEntry::TYPE_EARNED,
        'amount' => 5000,
        'recognized_through' => '2026-09-10',
    ]);

    $this->key = "payout:{$this->instructor->id}:2026-09-30";
});

function balance(int $instructorId): int
{
    return (int) LedgerEntry::where('instructor_id', $instructorId)->sum('amount');
}

it('never double pays when the payout command runs twice', function () {
    $this->provider->forcedOutcome = 'success';

    $this->artisan('ledger:payout', ['--date' => '2026-09-30'])->assertSuccessful();
    $this->artisan('ledger:payout', ['--date' => '2026-09-30'])->assertSuccessful();

    expect(Payout::count())->toBe(1)
        ->and($this->provider->transfers($this->key))->toBe(1)
        ->and(balance($this->instructor->id))->toBe(0);
});

it('never double pays when the job is retried or duplicated', function () {
    $this->provider->forcedOutcome = 'success';
    $this->artisan('ledger:payout', ['--date' => '2026-09-30']);
    $payoutId = Payout::first()->id;

    ProcessPayout::dispatchSync($payoutId);
    ProcessPayout::dispatchSync($payoutId);

    expect($this->provider->transfers($this->key))->toBe(1)
        ->and(Payout::find($payoutId)->status)->toBe(Payout::STATUS_SUCCEEDED);
});

it('never resends after a timeout, and reconciles to success', function () {
    $this->provider->forcedOutcome = 'timeout';
    $this->artisan('ledger:payout', ['--date' => '2026-09-30']);
    $payout = Payout::first();

    expect($payout->status)->toBe(Payout::STATUS_UNKNOWN)
        ->and($this->provider->transfers($this->key))->toBe(1);

    ProcessPayout::dispatchSync($payout->id); // a retry must not resend
    $this->artisan('ledger:reconcile')->assertSuccessful();

    expect($payout->fresh()->status)->toBe(Payout::STATUS_SUCCEEDED)
        ->and($this->provider->transfers($this->key))->toBe(1)
        ->and(balance($this->instructor->id))->toBe(0);
});

it('releases the money when the provider fails permanently', function () {
    $this->provider->forcedOutcome = 'fail';
    $this->artisan('ledger:payout', ['--date' => '2026-09-30']);

    expect(Payout::first()->status)->toBe(Payout::STATUS_FAILED)
        ->and($this->provider->transfers($this->key))->toBe(0)
        ->and(balance($this->instructor->id))->toBe(5000);

    $this->provider->forcedOutcome = 'success';
    $this->artisan('ledger:payout', ['--date' => '2026-10-01']);

    expect(balance($this->instructor->id))->toBe(0)
        ->and(Payout::where('status', Payout::STATUS_SUCCEEDED)->sum('amount'))->toEqual(5000);
});

it('recovers when a worker crashes after the money moved', function () {
    Queue::fake();
    $this->artisan('ledger:payout', ['--date' => '2026-09-30']);
    $payout = Payout::first();

    // Simulate: the worker claimed it, the provider paid, then the worker died.
    $payout->update(['status' => Payout::STATUS_PROCESSING]);
    $this->provider->forcedOutcome = 'success';
    $this->provider->send($this->key, $this->instructor->id, $payout->amount);

    $this->travel(15)->minutes();
    $this->artisan('ledger:reconcile')->assertSuccessful();

    expect($payout->fresh()->status)->toBe(Payout::STATUS_SUCCEEDED)
        ->and($this->provider->transfers($this->key))->toBe(1);
});
