<?php

namespace Database\Factories;

use App\Models\ScheduleBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScheduleBlock> */
class ScheduleBlockFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['title' => 'Venue maintenance', 'church' => 'San Isidro Labrador Chapel', 'starts_at' => now()->addDay()->setTime(9, 0), 'ends_at' => now()->addDay()->setTime(11, 0)];
    }
}
