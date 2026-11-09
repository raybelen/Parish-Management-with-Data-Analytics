<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\PaymentAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAdjustment>
 */
class PaymentAdjustmentFactory extends Factory
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
            'requested_by' => User::factory()->admin(),
            'idempotency_key' => fake()->uuid(),
            'kind' => 'fee',
            'payload' => ['fee' => '50.00', 'previous_fee_cents' => 10000, 'version' => 1],
            'reason' => 'Requested fee adjustment',
        ];
    }
}
