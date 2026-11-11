<?php

namespace Database\Factories;

use App\Models\Donation;
use App\Models\DonationAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DonationAdjustment> */
class DonationAdjustmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'donation_id' => Donation::factory(), 'idempotency_key' => (string) Str::uuid(),
            'kind' => 'void', 'donation_version' => 1, 'before' => [], 'payload' => [],
            'reason' => 'Duplicate entry', 'status' => 'pending', 'requested_by' => User::factory()->admin(),
        ];
    }
}
