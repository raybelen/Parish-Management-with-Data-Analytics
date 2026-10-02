<?php

namespace Tests\Feature;

use App\Models\Appointment;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_choice_and_new_request_pages_render_the_appointment_flow(): void
    {
        $this->withoutVite();

        $this->get(route('appointments.index'))
            ->assertSee('What would you like to do?')
            ->assertSee('Make a new appointment')
            ->assertSee('Manage my existing appointment')
            ->assertSee('href="'.route('appointments.create').'"', false)
            ->assertSee('href="'.route('appointments.lookup.create').'"', false);

        $response = $this->get(route('appointments.create'));
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);

        $response->assertSee('Client Information')
            ->assertSee('Service Selection')
            ->assertSee('Birth certificate')
            ->assertSee('Marriage license')
            ->assertSee('Death certificate');
        $this->assertSame(3, $xpath->query('//section[@data-service-fields and @hidden]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-date-offset]')->length);
        $this->assertSame(6, $xpath->query('//*[@data-time-value]')->length);
        $this->assertSame('900', $xpath->query('//*[@data-preferred-time]')->item(0)?->getAttribute('step'));
        $this->assertSame(1, $xpath->query('//*[@data-required-alert and @role="alert"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-schedule-fields]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-schedule-fields]//select[@id="preferred_church"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-schedule-fields]//*[@data-time-controls]')->length);
        $this->assertSame(6, $xpath->query('//select[contains(concat(" ", normalize-space(@class), " "), " parish-select ")]')->length);
    }

    public function test_set_appointment_calls_to_action_open_the_choice_page(): void
    {
        $this->withoutVite();
        $response = $this->get(route('home'));
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $appointmentLinks = (new DOMXPath($document))->query('//a[contains(normalize-space(.), "Set an Appointment")]');

        $this->assertGreaterThanOrEqual(5, $appointmentLinks->length);

        foreach ($appointmentLinks as $appointmentLink) {
            $this->assertSame(route('appointments.index'), $appointmentLink->getAttribute('href'));
        }
    }

    /**
     * @param  'baptism'|'wedding'|'funeral'  $serviceType
     */
    #[DataProvider('serviceTypes')]
    public function test_valid_service_request_creates_pending_appointment_and_stores_documents(string $serviceType): void
    {
        $this->travelTo('2026-09-27 09:00:00');
        config()->set('appointments.document_disk', 'supabase');
        Storage::fake('supabase');

        $response = $this->post(route('appointments.store'), $this->validPayload($serviceType));

        $response->assertRedirect();
        $appointment = Appointment::query()->sole();
        $this->assertSame($serviceType, $appointment->service_type);
        $this->assertSame('pending', $appointment->status);
        $this->assertMatchesRegularExpression('/^APT-260927-[A-Z0-9]{6}$/', $appointment->reference_number);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'client_email' => 'maria@example.com',
            'client_contact_key' => '09123456789',
        ]);

        $documentPaths = collect($appointment->documents)->flatten(1)->pluck('path')->all();
        Storage::disk('supabase')->assertExists($documentPaths);

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->get($location)
            ->assertSee($appointment->reference_number)
            ->assertSee('Pending')
            ->assertSee(config("appointments.services.{$serviceType}.label").' request');
    }

    public function test_request_rejects_missing_service_details_and_required_documents_without_creating_record(): void
    {
        $this->travelTo('2026-09-27 09:00:00');
        Storage::fake('local');
        $payload = $this->validPayload('baptism');
        $payload['service'] = [];
        unset($payload['documents']);

        $response = $this->post(route('appointments.store'), $payload);

        $response->assertSessionHasErrors([
            'service.child_full_name',
            'service.child_date_of_birth',
            'documents.birth_certificate',
        ]);
        $this->assertDatabaseEmpty('appointments');
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_request_rejects_irrelevant_service_fields(): void
    {
        $this->travelTo('2026-09-27 09:00:00');
        Storage::fake('local');
        $payload = $this->validPayload('baptism');
        $payload['service']['bride_full_name'] = 'Unexpected Bride';

        $response = $this->post(route('appointments.store'), $payload);

        $response->assertSessionHasErrors('service');
        $this->assertDatabaseEmpty('appointments');
    }

    public function test_request_normalizes_names_contact_details_and_spacing_before_storage(): void
    {
        $this->travelTo('2026-09-27 09:00:00');
        Storage::fake('local');
        $payload = $this->validPayload('funeral');
        $payload['client_full_name'] = '  MARIA   dela CRUZ  ';
        $payload['client_contact_number'] = '+63 (912) 345-6789';
        $payload['client_email'] = '  MARIA.DELA.CRUZ@EXAMPLE.COM  ';
        $payload['client_address'] = '  123   PARISH road, CEBU city  ';
        $payload['service']['contact_full_name'] = '  JUAN   dela cruz  ';
        $payload['service']['contact_number'] = '0912-345-6789';
        $payload['service']['contact_email'] = '  JUAN@EXAMPLE.COM ';
        $payload['service']['contact_relationship'] = '  BROTHER  ';

        $this->post(route('appointments.store'), $payload)->assertSessionHasNoErrors();

        $appointment = Appointment::query()->sole();
        $this->assertSame('Maria Dela Cruz', $appointment->client_full_name);
        $this->assertSame('+639123456789', $appointment->client_contact_number);
        $this->assertSame('maria.dela.cruz@example.com', $appointment->client_email);
        $this->assertSame('123 Parish Road, Cebu City', $appointment->client_address);
        $this->assertSame('Juan Dela Cruz', $appointment->service_details['contact_full_name']);
        $this->assertSame('09123456789', $appointment->service_details['contact_number']);
        $this->assertSame('juan@example.com', $appointment->service_details['contact_email']);
        $this->assertSame('Brother', $appointment->service_details['contact_relationship']);
    }

    public function test_request_rejects_invalid_email_and_phone_values(): void
    {
        $this->travelTo('2026-09-27 09:00:00');
        Storage::fake('local');
        $payload = $this->validPayload('baptism');
        $payload['client_email'] = 'not an email';
        $payload['client_contact_number'] = '0912-CALL-ME';

        $this->post(route('appointments.store'), $payload)
            ->assertSessionHasErrors(['client_email', 'client_contact_number']);

        $this->assertDatabaseEmpty('appointments');
    }

    public function test_unsigned_confirmation_link_is_forbidden(): void
    {
        $appointment = Appointment::factory()->create();

        $this->get(route('appointments.show', $appointment))->assertForbidden();
    }

    public function test_confirmation_escapes_client_supplied_content(): void
    {
        $unsafeValue = '<script>alert("unsafe")</script>';
        $appointment = Appointment::factory()->create([
            'client_full_name' => $unsafeValue,
            'service_details' => [
                ...Appointment::factory()->make()->service_details,
                'notes' => $unsafeValue,
            ],
        ]);
        $url = URL::temporarySignedRoute('appointments.show', now()->addMinutes(5), ['appointment' => $appointment]);

        $response = $this->get($url);

        $response->assertSee($unsafeValue)
            ->assertDontSee($unsafeValue, false);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function serviceTypes(): array
    {
        return [
            'baptism' => ['baptism'],
            'wedding' => ['wedding'],
            'funeral' => ['funeral'],
        ];
    }

    /**
     * @param  'baptism'|'wedding'|'funeral'  $serviceType
     * @return array<string, mixed>
     */
    private function validPayload(string $serviceType): array
    {
        $serviceDetails = match ($serviceType) {
            'baptism' => [
                'child_full_name' => 'Ana Santos',
                'child_date_of_birth' => '2026-05-12',
                'child_place_of_birth' => 'Cebu City',
                'child_sex' => 'female',
                'father_name' => 'Jose Santos',
                'mother_name' => 'Maria Santos',
                'godfather_name' => 'Pedro Cruz',
                'godmother_name' => 'Luz Cruz',
                'additional_godparents' => null,
                'special_requests' => null,
                'notes' => null,
            ],
            'wedding' => [
                'bride_full_name' => 'Maria Santos',
                'groom_full_name' => 'Juan Reyes',
                'bride_contact_number' => '0912 345 6789',
                'groom_contact_number' => '0998 765 4321',
                'current_address' => '123 Parish Road, Cebu City',
                'expected_guest_count' => 120,
                'civil_status' => 'single',
                'previous_marriage' => null,
                'marriage_license_status' => 'secured',
                'preferred_priest' => null,
                'principal_sponsors' => 'Pedro and Luz Cruz',
                'witnesses' => 'Ana and Carlo Reyes',
            ],
            'funeral' => [
                'deceased_full_name' => 'Roberto Santos',
                'date_of_birth' => '1946-02-03',
                'date_of_death' => '2026-09-25',
                'place_of_death' => 'Cebu City',
                'age' => 80,
                'residence_address' => '123 Parish Road, Cebu City',
                'funeral_home' => 'Peace Memorial Home',
                'wake_location' => 'Santos Residence',
                'cemetery_location' => 'Cebu Memorial Park',
                'expected_attendee_count' => 100,
                'contact_full_name' => 'Maria Santos',
                'contact_relationship' => 'Daughter',
                'contact_number' => '0912 345 6789',
                'contact_email' => 'maria@example.com',
                'contact_address' => '123 Parish Road, Cebu City',
                'special_requests' => null,
                'priest_notes' => null,
            ],
        };

        $documents = match ($serviceType) {
            'baptism' => [
                'birth_certificate' => [UploadedFile::fake()->create('birth-certificate.pdf', 100, 'application/pdf')],
            ],
            'wedding' => [
                'baptismal_certificates' => [UploadedFile::fake()->create('baptismal-certificates.pdf', 100, 'application/pdf')],
                'confirmation_certificates' => [UploadedFile::fake()->create('confirmation-certificates.pdf', 100, 'application/pdf')],
                'marriage_license' => [UploadedFile::fake()->create('marriage-license.pdf', 100, 'application/pdf')],
                'pre_marriage_seminar_certificate' => [UploadedFile::fake()->create('seminar-certificate.pdf', 100, 'application/pdf')],
            ],
            'funeral' => [
                'death_certificate' => [UploadedFile::fake()->create('death-certificate.pdf', 100, 'application/pdf')],
            ],
        };

        return [
            'client_full_name' => 'Maria Santos',
            'client_contact_number' => '0912 345 6789',
            'client_email' => 'MARIA@example.com',
            'client_address' => '123 Parish Road, Cebu City',
            'preferred_contact_method' => 'email',
            'service_type' => $serviceType,
            'preferred_date' => '2026-10-27',
            'preferred_time' => '09:30',
            'preferred_church' => 'St. John Nepomucene Parish Church',
            'additional_notes' => 'Please call in the afternoon.',
            'service' => $serviceDetails,
            'documents' => $documents,
        ];
    }
}
