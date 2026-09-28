<?php

namespace App\Ledger;

class EarningsCalculator
{
    /**
     * Instructor pool for a payment. The platform cut is rounded down,
     * and the pool is whatever remains, so cut + pool always equals amount.
     */
    public function instructorPool(int $amount, int $shareBps): int
    {
        $platformCut = intdiv($amount * (10000 - $shareBps), 10000);

        return $amount - $platformCut;
    }

    /**
     * How much of the pool is earned after $elapsedDays of a $termDays term.
     * Clamp elapsed to 0..termDays first.
     */
    public function earnedToDate(int $pool, int $elapsedDays, int $termDays): int
    {
        $elapsed = max(0, min($elapsedDays, $termDays));

        return intdiv($pool * $elapsed, $termDays);
    }

    /**
     * Split $total by weight. $weights is [instructorId => weight].
     * Returns [instructorId => amount]. Leftover piastres are not assigned.
     */
    public function split(int $total, array $weights): array
    {
        $totalWeight = array_sum($weights);

        if ($totalWeight === 0) {
            return [];
        }

        $shares = [];
        foreach ($weights as $instructorId => $weight) {
            $shares[$instructorId] = intdiv($total * $weight, $totalWeight);
        }

        return $shares;
    }
}
