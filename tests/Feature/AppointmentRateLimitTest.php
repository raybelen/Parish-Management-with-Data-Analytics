<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AppointmentRateLimitTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rejects_a_sixth_appointment_submission_from_the_same_ip_within_one_minute(): void
    {
        $payload = $this->invalidPayload();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('appointments.store'), $payload)
                ->assertSessionHasErrors('client_full_name');
        }

        $this->post(route('appointments.store'), $payload)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_rejects_an_eleventh_appointment_submission_for_the_same_contact_and_ip_within_one_hour(): void
    {
        $payload = $this->invalidPayload();

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->post(route('appointments.store'), $payload)
                ->assertSessionHasErrors('client_full_name');
            $this->travel(2)->minutes();
        }

        $this->post(route('appointments.store'), $payload)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_rejects_a_thirty_first_appointment_submission_from_the_same_ip_within_one_hour(): void
    {
        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $payload = $this->invalidPayload();
            $payload['client_email'] = 'client'.$attempt.'@example.com';

            $this->post(route('appointments.store'), $payload)
                ->assertSessionHasErrors('client_full_name');
            $this->travel(1)->minutes();
        }

        $payload = $this->invalidPayload();
        $payload['client_email'] = 'another-client@example.com';

        $this->post(route('appointments.store'), $payload)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    /** @return array<string, string> */
    private function invalidPayload(): array
    {
        return [
            'client_email' => 'maria@example.com',
            'client_contact_number' => '09123456789',
        ];
    }
}
