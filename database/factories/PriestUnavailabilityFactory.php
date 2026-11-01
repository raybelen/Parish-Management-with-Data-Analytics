<?php

namespace Database\Factories;

use App\Models\Personnel;
use App\Models\PriestUnavailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriestUnavailability>
 */
class PriestUnavailabilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['personnel_id' => Personnel::factory(), 'unavailable_on' => fake()->dateTimeBetween('+1 day', '+1 month')->format('Y-m-d')];
    }
}
