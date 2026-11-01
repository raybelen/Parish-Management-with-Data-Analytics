<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $publicId = (string) Str::uuid();

        return [
            'public_id' => $publicId,
            'appointment_id' => Appointment::factory(),
            'provider' => 'xendit',
            'environment' => 'sandbox',
            'reference_id' => 'pay'.str_replace('-', '', $publicId),
            'provider_session_id' => 'ps-'.Str::lower(Str::random(24)),
            'provider_business_id' => 'business-sandbox',
            'amount_cents' => 10000,
            'currency' => 'PHP',
            'status' => 'pending',
            'checkout_url' => 'https://checkout-staging.xendit.co/sessions/test-session',
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
