<?php

namespace Database\Seeders;

use App\Models\Schedule;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('local', 'testing')) {
            Schedule::factory()->count(5)->create(['source' => 'demo']);
        }
    }
}
