<?php

namespace Database\Factories;

use App\Models\ParishSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ParishSetting> */
class ParishSettingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['key' => fake()->unique()->word(), 'value' => []];
    }
}
