<?php

namespace Tests\Feature;

use App\Models\Appointment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentLookupControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manage_page_asks_for_reference_and_contact_information(): void
    {
        $this->withoutVite();

        $this->get(route('appointments.lookup.create'))
            ->assertSee('Find your appointment')
            ->assertSee('Appointment / reference number')
            ->assertSee('Email address or contact number');
    }

    #[DataProvider('matchingContacts')]
    public function test_matching_reference_and_contact_redirects_to_signed_appointment(string $contactInformation): void
    {
        $appointment = Appointment::factory()->create([
            'client_email' => 'maria@example.com',
            'client_contact_number' => '+63 912 345 6789',
            'client_contact_key' => '639123456789',
        ]);

        $response = $this->post(route('appointments.lookup.store'), [
            'reference_number' => mb_strtolower($appointment->reference_number),
            'contact_information' => $contactInformation,
        ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->get($location)
            ->assertSee($appointment->reference_number)
            ->assertSee($appointment->client_full_name);
    }

    public function test_nonmatching_contact_returns_generic_error_without_exposing_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'client_email' => 'maria@example.com',
            'client_contact_key' => '09123456789',
        ]);

        $response = $this->from(route('appointments.lookup.create'))->post(route('appointments.lookup.store'), [
            'reference_number' => $appointment->reference_number,
            'contact_information' => 'someone-else@example.com',
        ]);

        $response->assertRedirect(route('appointments.lookup.create'))
            ->assertSessionHasErrors([
                'reference_number' => 'We could not find an appointment matching those details.',
            ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function matchingContacts(): array
    {
        return [
            'email address' => ['MARIA@example.com'],
            'normalized phone number' => ['+63 (912) 345-6789'],
        ];
    }
}
