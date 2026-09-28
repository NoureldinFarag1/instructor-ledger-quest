<?php

use App\Ledger\EarningsCalculator;

beforeEach(fn () => $this->calc = new EarningsCalculator);

it('rounds the platform cut and gives the rest to the pool', function () {
    expect($this->calc->instructorPool(9999, 7000))->toBe(7000);
});

it('recognizes with a running total so fractions are never lost', function () {
    expect($this->calc->earnedToDate(6999, 4, 30))->toBe(933)
        ->and($this->calc->earnedToDate(6999, 30, 30))->toBe(6999);
});

it('never recognizes more than the pool or less than zero', function () {
    expect($this->calc->earnedToDate(6999, 45, 30))->toBe(6999)
        ->and($this->calc->earnedToDate(6999, -3, 30))->toBe(0);
});

it('splits equal weights and leaves the leftover unassigned', function () {
    $shares = $this->calc->split(7000, [1 => 1, 2 => 1, 3 => 1]);

    expect($shares)->toBe([1 => 2333, 2 => 2333, 3 => 2333])
        ->and(7000 - array_sum($shares))->toBe(1);
});

it('never splits more than the total, whatever the weights', function () {
    foreach ([[1, 1, 1], [7, 3], [1, 2, 3, 4], [999, 1]] as $weights) {
        $shares = $this->calc->split(100, $weights);
        $leftover = 100 - array_sum($shares);

        expect($leftover)->toBeGreaterThanOrEqual(0)
            ->toBeLessThan(count($weights));
    }
});

it('returns nothing when there are no instructors', function () {
    expect($this->calc->split(7000, []))->toBe([]);
});
