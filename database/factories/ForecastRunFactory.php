<?php

namespace Database\Factories;

use App\Models\ForecastRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ForecastRun> */
class ForecastRunFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['source' => 'operational', 'status' => 'insufficient_data', 'configuration' => [], 'history' => []];
    }
}
