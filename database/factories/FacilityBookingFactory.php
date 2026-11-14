<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\FacilityBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacilityBooking>
 */
class FacilityBookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'kind' => 'reservation',
            'title' => 'Parish council meeting',
            'organizer' => 'Parish council',
            'activity' => 'Meeting',
            'attendees' => 20,
            'starts_at' => now()->addDay()->setTime(9, 0),
            'ends_at' => now()->addDay()->setTime(10, 0),
            'status' => 'confirmed',
        ];
    }
}
