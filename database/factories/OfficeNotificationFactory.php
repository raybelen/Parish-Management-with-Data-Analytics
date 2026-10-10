<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\OfficeNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OfficeNotification> */
class OfficeNotificationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['appointment_id' => Appointment::factory(), 'event' => 'new', 'message' => 'New appointment request', 'deduplication_key' => (string) Str::uuid()];
    }
}
