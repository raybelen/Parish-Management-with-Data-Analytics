<?php

namespace Database\Factories;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Schedule> */
class ScheduleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['source' => 'operational', 'title' => fake()->sentence(3), 'category' => 'Meeting', 'starts_at' => '2026-11-02 10:00:00', 'ends_at' => '2026-11-02 11:00:00', 'status' => 'scheduled', 'recurrence' => 'none', 'version' => 1];
    }
}
