<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AppointmentParticipant> */
class AppointmentParticipantFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['appointment_id' => Appointment::factory(), 'role' => 'child', 'full_name' => fake()->name(), 'date_of_birth' => '2025-01-01'];
    }
}
