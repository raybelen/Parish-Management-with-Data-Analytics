<?php

namespace Tests\Feature;

use App\Mail\AppointmentRequestReceived;
use App\Models\Appointment;
use App\Models\ParishSetting;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
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
            ->assertSee('Documents to prepare')
            ->assertSee('Photocopy of Birth Certificate with registry number (PSA or Local Civil Registry)')
            ->assertSee('Marriage Contract, if parents are married')
            ->assertSee('PSA Birth Certificate')
            ->assertSee('Updated Baptismal & Confirmation Certificate (Purpose: For Marriage)')
            ->assertSee('Marriage License, or, if civilly married, Marriage Contract with registry number')
            ->assertSee('Pre-Cana Seminar')
            ->assertSee('Photocopy of Death Certificate')
            ->assertSee('St. John Nepomucene Parish')
            ->assertSee('San Isidro Labrador Chapel')
            ->assertSee('Nuestra Señora de los Desamparados')
            ->assertSee('San Fernando Chapel')
            ->assertSee('Santa Misericordia Chapel');
        $this->assertSame(0, $xpath->query('//form[@data-appointment-form]//input[@type="file"]')->length);
        $this->assertSame(0, $xpath->query('//form[@data-appointment-form and @enctype="multipart/form-data"]')->length);
        $this->assertSame(3, $xpath->query('//section[@data-service-fields and @hidden]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-date-offset]')->length);
        $this->assertSame(6, $xpath->query('//*[@data-time-value]')->length);
        $this->assertSame('900', $xpath->query('//*[@data-preferred-time]')->item(0)?->getAttribute('step'));
        $this->assertSame(1, $xpath->query('//*[@data-required-alert and @role="alert"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-schedule-fields]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-schedule-fields]//select[@id="preferred_church"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-schedule-fields]//*[@data-time-controls]')->length);
        $this->assertSame(8, $xpath->query('//select[contains(concat(" ", normalize-space(@class), " "), " parish-select ") and not(ancestor::*[@data-psgc-address])]')->length);
        $this->assertSame(11, $xpath->query('//*[@data-psgc-address]')->length);
        $this->assertSame(11, $xpath->query('//input[@type="hidden" and @data-psgc-address-output]')->length);
        $this->assertSame(11, $xpath->query('//input[@type="hidden" and @data-required-label]')->length);
        $this->assertSame(11, $xpath->query('//*[@data-psgc-address-preview]')->length);
        $this->assertSame(11, $xpath->query('//input[@type="text" and @data-psgc-address-detail]')->length);
        $this->assertSame(44, $xpath->query('//*[@data-psgc-address]//select')->length);
        foreach (['father_origin_address', 'mother_origin_address', 'parents_current_residence', 'groom_status', 'bride_status', 'groom_mother_maiden_name', 'bride_mother_maiden_name', 'anointing_of_sick', 'cause_of_death'] as $field) {
            $this->assertSame(1, $xpath->query('//*[@name="service['.$field.']"]')->length);
        }
        $this->assertSame(2, $xpath->query('//*[@name="service[godfather_address]"]')->length);
        $this->assertSame(2, $xpath->query('//*[@name="service[godmother_address]"]')->length);
        $this->assertSame(1, $xpath->query('//*[@name="service[groom_address]"]/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " sm:col-span-2 ")][1]')->length);
        $this->assertSame(1, $xpath->query('//*[@name="service[bride_address]"]/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " sm:col-span-2 ")][1]')->length);
        $this->assertSame(0, $xpath->query('//*[@name="service[godfather_address]"]/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " sm:col-span-2 ")][1]')->length);
        $this->assertSame(0, $xpath->query('//*[@name="service[godmother_address]"]/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " sm:col-span-2 ")][1]')->length);
        $this->assertSame(2, $xpath->query('//*[@name="service[godfather_name]"]/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " order-1 ") and contains(concat(" ", normalize-space(@class), " "), " sm:order-none ")][1]')->length);
        $this->assertSame(2, $xpath->query('//*[@name="service[godfather_address]"]/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " order-2 ") and contains(concat(" ", normalize-space(@class), " "), " sm:order-none ")][1]')->length);
        $this->assertSame(2, $xpath->query('//*[@name="service[godmother_name]"]/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " order-3 ") and contains(concat(" ", normalize-space(@class), " "), " sm:order-none ")][1]')->length);
        $this->assertSame(2, $xpath->query('//*[@name="service[godmother_address]"]/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " order-4 ") and contains(concat(" ", normalize-space(@class), " "), " sm:order-none ")][1]')->length);
        $this->assertSame(0, $xpath->query('//*[@name="service[parents_address]"]')->length);
        $this->assertSame(0, $xpath->query('//*[@name="service[godparents_address]"]')->length);
        $this->assertSame(0, $xpath->query('//*[@name="service[minister_name]"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-death-year and @readonly and not(@name)]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-burial-date and @readonly and not(@name)]')->length);
        foreach (['bride_contact_number', 'groom_contact_number', 'marriage_license_status', 'funeral_home', 'wake_location', 'contact_number', 'additional_godparents'] as $field) {
            $this->assertSame(0, $xpath->query('//*[@name="service['.$field.']"]')->length);
        }
    }

    public function test_new_request_page_requires_review_confirmation_before_submission(): void
    {
        $this->withoutVite();

        $response = $this->get(route('appointments.create'));
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);

        $response->assertSee('Review appointment request')
            ->assertSee('Check your information')
            ->assertSee('Confirm and submit')
            ->assertSee('Go back and edit');
        $this->assertSame(1, $xpath->query('//form[@data-appointment-form]//button[@type="submit" and @data-review-appointment]')->length);
        $this->assertSame(1, $xpath->query('//dialog[@id="confirm-appointment-dialog"]//button[@type="button" and @data-confirm-appointment]')->length);
        $this->assertSame(6, $xpath->query('//dialog[@id="confirm-appointment-dialog"]//dd[@*[starts-with(name(), "data-review-")]]')->length);
    }

    #[DataProvider('invalidServiceDetails')]
    public function test_new_service_details_are_validated(string $serviceType, string $field, mixed $value): void
    {
        $this->travelTo('2026-09-27 09:00:00');
        $payload = $this->validPayload($serviceType);
        unset($payload['documents']);
        $payload['service'][$field] = $value;

        $this->post(route('appointments.store'), $payload)->assertSessionHasErrors('service.'.$field);

        $this->assertDatabaseEmpty('appointments');
    }

    /** @return array<string, array{string, string, mixed}> */
    public static function invalidServiceDetails(): array
    {
        return [
            'father origin address missing' => ['baptism', 'father_origin_address', ''],
            'mother origin address missing' => ['baptism', 'mother_origin_address', ''],
            'parents residence missing' => ['baptism', 'parents_current_residence', ''],
            'godfather address missing for baptism' => ['baptism', 'godfather_address', ''],
            'godmother address missing for baptism' => ['baptism', 'godmother_address', ''],
            'future birthday' => ['baptism', 'child_date_of_birth', '2026-10-01'],
            'bride status invalid' => ['wedding', 'bride_status', 'invalid'],
            'groom status missing' => ['wedding', 'groom_status', ''],
            'bride age negative' => ['wedding', 'bride_age', -1],
            'groom age fractional' => ['wedding', 'groom_age', 25.5],
            'groom mother missing' => ['wedding', 'groom_mother_maiden_name', ''],
            'bride mother missing' => ['wedding', 'bride_mother_maiden_name', ''],
            'godfather address missing' => ['wedding', 'godfather_address', ''],
            'godmother address missing' => ['wedding', 'godmother_address', ''],
            'informant missing' => ['funeral', 'contact_full_name', ''],
            'relationship missing' => ['funeral', 'contact_relationship', ''],
            'sacrament invalid' => ['funeral', 'anointing_of_sick', 'sometimes'],
            'cause missing' => ['funeral', 'cause_of_death', ''],
            'future death date' => ['funeral', 'date_of_death', '2026-10-01'],
            'age too large' => ['funeral', 'age', 151],
        ];
    }

    public function test_funeral_confirmation_derives_year_and_burial_date_from_saved_dates(): void
    {
        $appointment = Appointment::factory()->create([
            'service_type' => 'funeral',
            'preferred_date' => '2026-11-10',
            'starts_at' => '2026-11-12 09:00:00',
            'service_details' => [
                'deceased_full_name' => 'Roberto Santos',
                'date_of_death' => '2026-09-25',
                'anointing_of_sick' => 'no',
                'cause_of_death' => 'As recorded on certificate',
            ],
        ]);

        $response = $this->get($this->oneTimeShowUrl($appointment));

        $response->assertSee('Year of death')
            ->assertSee('Date of burial / funeral Mass')
            ->assertSee('November 12, 2026')
            ->assertSee('Received anointing of the sick?')
            ->assertSee('As recorded on certificate');
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
    public function test_valid_service_request_creates_pending_appointment_without_document_uploads(string $serviceType): void
    {
        $this->travelTo('2026-09-27 09:00:00');
        Mail::fake();
        Storage::fake('local');

        $payload = $this->validPayload($serviceType);
        $response = $this->post(route('appointments.store'), $payload);

        $response->assertRedirect();
        $appointment = Appointment::query()->sole();
        $this->assertSame($serviceType, $appointment->service_type);
        $this->assertSame($payload['service'], $appointment->service_details);
        $this->assertSame('pending', $appointment->status);
        $this->assertSame('2026-10-27 '.($serviceType === 'wedding' ? '10:00' : '09:30'), $appointment->starts_at?->format('Y-m-d H:i'));
        $this->assertSame('2026-10-27 '.($serviceType === 'wedding' ? '11:00' : '10:30'), $appointment->ends_at?->format('Y-m-d H:i'));
        $this->assertSame('St. John Nepomucene Parish', $appointment->confirmed_church);
        $this->assertSame([], $appointment->documents);
        $this->assertMatchesRegularExpression('/^APT-260927-[A-Z0-9]{6}$/', $appointment->reference_number);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'client_email' => 'maria@example.com',
            'client_contact_key' => '09123456789',
        ]);
        Mail::assertQueued(AppointmentRequestReceived::class, function (AppointmentRequestReceived $mail) use ($appointment): bool {
            return $mail->appointment->is($appointment) && $mail->hasTo('maria@example.com');
        });

        Storage::disk('local')->assertDirectoryEmpty('/');

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->get($location)
            ->assertSee($appointment->reference_number)
            ->assertSee('Pending')
            ->assertSee('Documents to bring')
            ->assertSee(config("appointments.services.{$serviceType}.label").' request');
    }

    public function test_appointment_request_rejects_document_uploads(): void
    {
        $this->travelTo('2026-09-27 09:00:00');
        Storage::fake('local');
        $payload = $this->validPayload('baptism');
        $payload['documents'] = [
            'birth_certificate' => [UploadedFile::fake()->create('birth-certificate.pdf', 100, 'application/pdf')],
        ];

        $this->post(route('appointments.store'), $payload)
            ->assertSessionHasErrors([
                'documents' => 'Document uploads are not accepted with appointment requests. Please bring the listed requirements to the church office.',
            ]);

        $this->assertDatabaseEmpty('appointments');
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_request_without_an_email_does_not_queue_a_confirmation_email(): void
    {
        $this->travelTo('2026-09-27 09:00:00');
        Mail::fake();
        Storage::fake('local');
        $payload = $this->validPayload('baptism');
        $payload['preferred_contact_method'] = 'phone';
        $payload['client_email'] = null;

        $this->post(route('appointments.store'), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('appointments', 1);
        Mail::assertNothingQueued();
    }

    public function test_request_received_email_contains_the_reference_and_requested_schedule(): void
    {
        $appointment = Appointment::factory()->make([
            'reference_number' => 'APT-261014-ABC123',
            'client_full_name' => 'Maria Dela Cruz',
            'preferred_date' => '2026-11-20',
            'preferred_time' => '09:00',
        ]);

        $mail = new AppointmentRequestReceived($appointment);

        $mail->assertHasSubject('Appointment request received: APT-261014-ABC123');
        $mail->assertSeeInHtml('APT-261014-ABC123');
        $mail->assertSeeInHtml('November 20, 2026');
        $mail->assertSeeInText('pending document and payment verification');
    }

    #[DataProvider('regularMassConflicts')]
    public function test_regular_mass_conflicts_reject_requests_without_saving_or_sending_mail(string $date, string $time, string $venue): void
    {
        $this->travelTo('2026-09-27 09:00:00');
        Storage::fake('local');
        Mail::fake();
        $payload = [...$this->validPayload('baptism'), 'preferred_date' => $date, 'preferred_time' => $time, 'preferred_church' => $venue];

        $this->post(route('appointments.store'), $payload)
            ->assertSessionHasErrors('preferred_time');

        $this->assertDatabaseEmpty('appointments');
        $this->assertSame([], Storage::disk('local')->allFiles());
        Mail::assertNothingQueued();
    }

    public static function regularMassConflicts(): array
    {
        return [
            'daily Mass' => ['2026-10-27', '07:00', 'St. John Nepomucene Parish'],
            'ceremony overlaps Mass start' => ['2026-10-27', '06:30', 'St. John Nepomucene Parish'],
            'Mass turnaround gap' => ['2026-10-27', '08:00', 'St. John Nepomucene Parish'],
            'Wednesday evening' => ['2026-10-28', '17:00', 'St. John Nepomucene Parish'],
            'one-hour gap before Wednesday Mass' => ['2026-10-28', '16:00', 'St. John Nepomucene Parish'],
            'one-hour gap before Sunday chapel Mass' => ['2026-11-01', '08:00', 'San Fernando Chapel'],
            'chapel anticipated Mass' => ['2026-10-31', '17:00', 'San Isidro Labrador Chapel'],
            'Sunday Mass' => ['2026-11-01', '09:00', 'San Fernando Chapel'],
        ];
    }

    public function test_available_time_at_another_venue_is_accepted_as_pending(): void
    {
        $this->travelTo('2026-09-27 09:00:00');
        Storage::fake('local');
        Mail::fake();
        $payload = [...$this->validPayload('baptism'), 'preferred_date' => '2026-10-27', 'preferred_time' => '08:00', 'preferred_church' => 'San Isidro Labrador Chapel'];

        $this->post(route('appointments.store'), $payload)->assertSessionHasNoErrors();

        $appointment = Appointment::query()->sole();
        $this->assertSame('pending', $appointment->status);
        $this->assertSame('2026-10-27 08:00', $appointment->starts_at->format('Y-m-d H:i'));
    }

    public function test_conflicting_requested_slot_is_rejected_without_creating_another_request(): void
    {
        $this->travelTo('2026-09-27 09:00:00');
        Storage::fake('local');
        Appointment::factory()->create([
            'status' => 'pending',
            'starts_at' => '2026-10-27 09:00',
            'ends_at' => '2026-10-27 10:00',
            'reserved_until' => '2026-10-27 10:30',
            'confirmed_church' => 'St. John Nepomucene Parish',
        ]);

        $this->post(route('appointments.store'), $this->validPayload('baptism'))->assertSessionHasErrors('preferred_time');

        $this->assertDatabaseCount('appointments', 1);
        $this->assertSame('2026-10-27 09:00', Appointment::query()->sole()->starts_at->format('Y-m-d H:i'));
    }

    public function test_request_rejects_missing_service_details_without_creating_record(): void
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
        ]);
        $this->assertDatabaseEmpty('appointments');
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_request_rejects_irrelevant_service_fields(): void
    {
        $this->travelTo('2026-09-27 09:00:00');
        Storage::fake('local');
        $payload = $this->validPayload('baptism');
        $payload['service']['minister_name'] = 'Client-entered minister';

        $response = $this->post(route('appointments.store'), $payload);

        $response->assertSessionHasErrors('service');
        $this->assertDatabaseEmpty('appointments');
    }

    public function test_request_rejects_a_venue_disabled_by_the_parish_office(): void
    {
        $this->travelTo('2026-09-27 09:00:00');
        ParishSetting::query()->where('key', 'office')->update([
            'value' => ['active_venues' => ['San Fernando Chapel']],
        ]);
        $payload = $this->validPayload('baptism');
        $payload['preferred_church'] = 'St. John Nepomucene Parish';

        $this->post(route('appointments.store'), $payload)->assertSessionHasErrors([
            'preferred_church' => 'The selected church or chapel is currently unavailable.',
        ]);

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
        $payload['service']['cause_of_death'] = '  As recorded on certificate  ';
        $payload['service']['contact_relationship'] = '  BROTHER  ';

        $this->post(route('appointments.store'), $payload)->assertSessionHasNoErrors();

        $appointment = Appointment::query()->sole();
        $this->assertSame('Maria Dela Cruz', $appointment->client_full_name);
        $this->assertSame('+639123456789', $appointment->client_contact_number);
        $this->assertSame('maria.dela.cruz@example.com', $appointment->client_email);
        $this->assertSame('123 Parish Road, Cebu City', $appointment->client_address);
        $this->assertSame('Juan Dela Cruz', $appointment->service_details['contact_full_name']);
        $this->assertSame('As recorded on certificate', $appointment->service_details['cause_of_death']);
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
        $url = $this->oneTimeShowUrl($appointment);

        $response = $this->get($url);

        $response->assertSee($unsafeValue)
            ->assertDontSee($unsafeValue, false);
    }

    public function test_confirmation_link_is_session_bound_single_use_and_not_cacheable(): void
    {
        $this->withoutVite();
        $appointment = Appointment::factory()->create([
            'client_email' => 'maria@example.com',
        ]);
        $lookup = $this->post(route('appointments.lookup.store'), [
            'reference_number' => $appointment->reference_number,
            'contact_information' => 'maria@example.com',
        ]);
        $location = $lookup->headers->get('Location');
        $this->assertNotNull($location);

        $firstVisit = $this->get($location);

        $firstVisit
            ->assertSee($appointment->reference_number)
            ->assertSee('data-one-time-appointment-access', false)
            ->assertHeader('Pragma', 'no-cache');
        $this->assertStringContainsString('no-store', (string) $firstVisit->headers->get('Cache-Control'));

        $this->get($location)
            ->assertRedirect(route('appointments.lookup.create'))
            ->assertSessionHas('appointment_access_notice', 'Please verify the appointment reference and contact information again.');
    }

    public function test_copied_confirmation_link_cannot_be_opened_without_the_verifying_session(): void
    {
        $appointment = Appointment::factory()->create([
            'client_email' => 'maria@example.com',
        ]);
        $lookup = $this->post(route('appointments.lookup.store'), [
            'reference_number' => $appointment->reference_number,
            'contact_information' => 'maria@example.com',
        ]);
        $location = $lookup->headers->get('Location');
        $this->assertNotNull($location);

        $this->withSession(['appointment_access_tokens' => []])
            ->get($location)
            ->assertRedirect(route('appointments.lookup.create'));
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
                'father_origin_address' => '123 Parish Road, Cebu City',
                'mother_origin_address' => '321 Parish Road, Cebu City',
                'parents_current_residence' => '456 Parish Road, Cebu City',
                'godfather_name' => 'Pedro Cruz',
                'godmother_name' => 'Luz Cruz',
                'godfather_address' => '789 Parish Road, Cebu City',
                'godmother_address' => '987 Parish Road, Cebu City',
            ],
            'wedding' => [
                'groom_full_name' => 'Juan Reyes',
                'groom_status' => 'single',
                'groom_age' => 28,
                'groom_origin' => 'Cebu City',
                'groom_address' => '123 Parish Road, Cebu City',
                'groom_father_name' => 'Pedro Reyes',
                'groom_mother_maiden_name' => 'Luz Cruz',
                'bride_full_name' => 'Maria Santos',
                'bride_status' => 'single',
                'bride_age' => 26,
                'bride_origin' => 'Cebu City',
                'bride_address' => '456 Parish Road, Cebu City',
                'bride_father_name' => 'Jose Santos',
                'bride_mother_maiden_name' => 'Ana Cruz',
                'godfather_name' => 'Carlo Cruz',
                'godmother_name' => 'Rosa Cruz',
                'godfather_address' => '789 Parish Road, Cebu City',
                'godmother_address' => '789 Parish Road, Cebu City',
            ],
            'funeral' => [
                'deceased_full_name' => 'Roberto Santos',
                'date_of_death' => '2026-09-25',
                'age' => 80,
                'civil_status' => 'widowed',
                'residence_address' => '123 Parish Road, Cebu City',
                'contact_full_name' => 'Maria Santos',
                'contact_relationship' => 'Daughter',
                'anointing_of_sick' => 'yes',
                'cause_of_death' => 'As recorded on the death certificate',
                'cemetery_location' => 'Cebu Memorial Park',
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
            'preferred_time' => $serviceType === 'wedding' ? '10:00' : '09:30',
            'preferred_church' => 'St. John Nepomucene Parish',
            'additional_notes' => 'Please call in the afternoon.',
            'service' => $serviceDetails,
        ];
    }

    private function oneTimeShowUrl(Appointment $appointment): string
    {
        $accessToken = 'one-time-appointment-access-token';
        $this->withSession(['appointment_access_tokens' => [$accessToken => $appointment->id]]);

        return URL::temporarySignedRoute(
            'appointments.show',
            now()->addMinutes(5),
            ['appointment' => $appointment, 'access_token' => $accessToken],
        );
    }
}
