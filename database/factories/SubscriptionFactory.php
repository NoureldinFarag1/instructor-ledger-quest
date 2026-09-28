<?php

namespace Database\Factories;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan' => 'monthly',
            'amount' => 9999,
            'instructor_share_bps' => 7000,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-10-01',
        ];
    }
}
