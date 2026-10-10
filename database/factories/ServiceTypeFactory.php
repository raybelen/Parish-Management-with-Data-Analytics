<?php

namespace Database\Factories;

use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceType> */
class ServiceTypeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['slug' => fake()->unique()->lexify('service_??????'), 'name' => 'Parish service', 'duration_minutes' => 60, 'buffer_minutes' => 30, 'fee_cents' => 0, 'requirements' => [], 'active' => true];
    }
}
