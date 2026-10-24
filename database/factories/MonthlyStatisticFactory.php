<?php

namespace Database\Factories;

use App\Models\MonthlyStatistic;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MonthlyStatistic> */
class MonthlyStatisticFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['source' => 'operational', 'service_type' => 'baptism', 'month' => '2025-01-01', 'count' => 10, 'status' => 'confirmed', 'reference' => 'Verified parish register', 'confirmed_at' => now(), 'version' => 1];
    }
}
