<?php

namespace Database\Factories;

use App\Models\Personnel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Personnel> */
class PersonnelFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => 'Fr. '.fake()->name(), 'active' => true];
    }

    public function rotationPosition(int $position): static
    {
        return $this->state(fn (): array => ['rotation_position' => $position]);
    }
}
