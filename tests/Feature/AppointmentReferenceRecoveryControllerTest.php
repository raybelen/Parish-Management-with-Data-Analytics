<?php

namespace Tests\Feature;

use App\Models\Appointment;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AppointmentReferenceRecoveryControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manage_page_offers_reference_number_recovery(): void
    {
        $this->withoutVite();

        $this->get(route('appointments.lookup.create'))
            ->assertSee('Forgot your reference number?')
            ->assertSee('Full name used on the request')
            ->assertSee('action="'.route('appointments.reference-recovery.store').'"', false);
    }

    public function test_matching_name_and_email_recover_only_the_clients_appointments(): void
    {
        $firstAppointment = Appointment::factory()->create([
            'client_full_name' => 'Maria Dela Cruz',
            'client_email' => 'maria@example.com',
            'preferred_date' => '2026-11-10',
        ]);
        $secondAppointment = Appointment::factory()->create([
            'client_full_name' => 'Maria Dela Cruz',
            'client_email' => 'maria@example.com',
            'service_type' => 'wedding',
            'preferred_date' => '2026-12-12',
        ]);
        $differentClient = Appointment::factory()->create([
            'client_full_name' => 'Another Client',
            'client_email' => 'maria@example.com',
        ]);
        $differentContact = Appointment::factory()->create([
            'client_full_name' => 'Maria Dela Cruz',
            'client_email' => 'another@example.com',
        ]);

        $response = $this->post(route('appointments.reference-recovery.store'), [
            'recovery_full_name' => '  MARIA   dela CRUZ ',
            'recovery_contact_information' => ' MARIA@EXAMPLE.COM ',
        ]);

        $response
            ->assertSee('Your appointment references')
            ->assertSee($firstAppointment->reference_number)
            ->assertSee($secondAppointment->reference_number)
            ->assertDontSee($differentClient->reference_number)
            ->assertDontSee($differentContact->reference_number);

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $appointmentLink = (new DOMXPath($document))->query('//a[contains(normalize-space(.), "View appointment details")]')->item(0);
        $this->assertNotNull($appointmentLink);

        $this->get($appointmentLink->getAttribute('href'))
            ->assertSee($secondAppointment->reference_number)
            ->assertSee($secondAppointment->client_full_name);
    }

    public function test_matching_name_and_formatted_phone_number_recovers_an_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'client_full_name' => 'Juan Santos',
            'client_contact_number' => '+639123456789',
            'client_contact_key' => '639123456789',
        ]);

        $this->post(route('appointments.reference-recovery.store'), [
            'recovery_full_name' => 'Juan Santos',
            'recovery_contact_information' => '+63 (912) 345-6789',
        ])
            ->assertSee('Your appointment references')
            ->assertSee($appointment->reference_number);
    }

    public function test_nonmatching_recovery_details_return_a_generic_error(): void
    {
        Appointment::factory()->create([
            'client_full_name' => 'Maria Santos',
            'client_email' => 'maria@example.com',
        ]);

        $response = $this->from(route('appointments.lookup.create'))->post(route('appointments.reference-recovery.store'), [
            'recovery_full_name' => 'Different Person',
            'recovery_contact_information' => 'maria@example.com',
        ]);

        $response->assertRedirect(route('appointments.lookup.create'))
            ->assertSessionHasErrors([
                'recovery_full_name' => 'We could not find an appointment matching those details.',
            ], null, 'recovery');
    }

    public function test_recovery_requires_both_identity_fields(): void
    {
        $response = $this->post(route('appointments.reference-recovery.store'));

        $response->assertSessionHasErrors([
            'recovery_full_name' => 'Please enter the full name used on the appointment request.',
            'recovery_contact_information' => 'Please enter the email address or contact number used on the request.',
        ], null, 'recovery');
    }
}
