<?php

namespace Database\Factories;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Facility>
 */
class FacilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'operational',
            'name' => fake()->unique()->words(3, true),
            'capacity' => 100,
            'active' => true,
            'opening_hours' => array_fill(1, 7, ['open' => true, 'opens_at' => '08:00', 'closes_at' => '18:00']),
            'permitted_activities' => ['Meeting', 'Parish gathering', 'Catechism class'],
        ];
    }
}
