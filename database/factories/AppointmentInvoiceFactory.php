<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AppointmentInvoice>
 */
class AppointmentInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'number' => 'INV-'.Str::uuid(),
            'revision' => 1,
            'parish_name' => 'Test parish',
            'client_name' => fake()->name(),
            'appointment_reference' => 'APT-TEST',
            'service_label' => 'Baptism',
            'source' => 'operational',
            'amount_cents' => 10000,
            'issued_at' => now(),
        ];
    }
}
