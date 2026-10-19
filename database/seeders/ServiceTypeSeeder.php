<?php

namespace Database\Seeders;

use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class ServiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('appointments.services') as $slug => $definition) {
            ServiceType::firstOrCreate(['slug' => $slug], ['name' => $definition['label'], 'duration_minutes' => 60, 'buffer_minutes' => 30, 'fee_cents' => 0, 'requirements' => $definition['documents']]);
        }
    }
}
