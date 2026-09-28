<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $instructors = \App\Models\Instructor::factory()->count(4)->create();

        \App\Models\Subscription::factory()->count(20)->create()->each(function ($sub) use ($instructors) {
            $sub->instructors()->attach(
                $instructors->random(rand(1, 3))->mapWithKeys(fn ($i) => [$i->id => ['weight' => rand(10, 120)]])
            );
        });
    }
}
