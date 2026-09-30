<?php

namespace Database\Factories;

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $contactNumber = fake()->numerify('09#########');

        return [
            'client_full_name' => fake()->name(),
            'client_contact_number' => $contactNumber,
            'client_contact_key' => $contactNumber,
            'client_email' => fake()->safeEmail(),
            'client_address' => fake()->address(),
            'preferred_contact_method' => 'email',
            'service_type' => 'baptism',
            'preferred_date' => fake()->dateTimeBetween('+1 week', '+6 months')->format('Y-m-d'),
            'preferred_time' => '09:00',
            'preferred_church' => 'St. John Nepomucene Parish Church',
            'additional_notes' => null,
            'service_details' => [
                'child_full_name' => fake()->name(),
                'child_date_of_birth' => fake()->dateTimeBetween('-1 year', '-1 month')->format('Y-m-d'),
                'child_place_of_birth' => fake()->city(),
                'child_sex' => 'female',
                'father_name' => fake()->name('male'),
                'mother_name' => fake()->name('female'),
                'godfather_name' => fake()->name('male'),
                'godmother_name' => fake()->name('female'),
                'additional_godparents' => null,
                'special_requests' => null,
                'notes' => null,
            ],
            'documents' => [],
        ];
    }
}
