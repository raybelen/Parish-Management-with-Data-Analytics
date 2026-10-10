<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['appointment_id' => Appointment::factory(), 'kind' => 'payment', 'amount_cents' => 10000, 'method' => 'cash', 'paid_at' => now(), 'notes' => 'Receipt recorded', 'idempotency_key' => (string) Str::uuid()];
    }
}
