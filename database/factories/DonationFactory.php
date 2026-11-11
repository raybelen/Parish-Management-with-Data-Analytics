<?php

namespace Database\Factories;

use App\Models\Donation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Donation> */
class DonationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'receipt_number' => 'DON-'.Str::upper((string) Str::ulid()), 'idempotency_key' => (string) Str::uuid(),
            'submission_hash' => hash('sha256', 'factory'), 'source' => 'operational',
            'donor_name' => fake()->name(), 'amount_cents' => 10000, 'donated_on' => now()->toDateString(),
            'category' => 'general', 'method' => 'cash', 'status' => 'recorded', 'version' => 1,
            'recorded_by' => User::factory()->admin(),
        ];
    }
}
