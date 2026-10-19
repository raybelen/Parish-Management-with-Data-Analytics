<?php

namespace Database\Factories;

use App\Models\ServiceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceRecord> */
class ServiceRecordFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['record_number' => 'ARCH-'.fake()->unique()->numerify('########'), 'source' => 'archival', 'service_type' => 'baptism', 'service_date' => '2020-01-01', 'participant_names' => fake()->name(), 'church' => 'San Isidro Labrador Chapel', 'details' => [], 'participants' => []];
    }
}
