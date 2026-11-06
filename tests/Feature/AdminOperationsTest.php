<?php

namespace Tests\Feature;

use App\Mail\AppointmentConfirmed;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ParishSetting;
use App\Models\Payment;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_office_pages_require_authentication_and_mfa(): void
    {
        $this->get('/admin/appointments')->assertRedirect('/login');
        $this->actingAs(User::factory()->staff()->create())->get('/admin/appointments')->assertRedirect('/admin/security');
    }

    public function test_admin_can_render_all_office_pages_and_appointment_details(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create();

        foreach (['', '/appointments', '/appointments?view=calendar', '/appointments/create', '/records', '/records/create', '/reports', '/forecasts', '/settings', '/website-content', '/users', '/notifications', '/audit', '/appointments/'.$appointment->reference_number] as $path) {
            $this->get('/admin'.$path)->assertOk();
        }
    }

    public function test_calendar_navigation_keeps_search_and_service_filters(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());

        $this->get('/admin/appointments?view=calendar&month=2026-11&q=Maria&service=baptism')
            ->assertSee(route('admin.appointments.index', ['q' => 'Maria', 'service' => 'baptism', 'view' => 'calendar', 'month' => '2026-12']))
            ->assertSee(route('admin.appointments.index', ['q' => 'Maria', 'service' => 'baptism', 'month' => '2026-11', 'view' => 'list']));
    }

    public function test_pending_appointment_shows_requirements_before_status_without_completion_input(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create(['status' => 'pending']);

        $this->get('/admin/appointments/'.$appointment->reference_number)
            ->assertSeeInOrder(['id="appointment-documents"', 'id="appointment-payments"', 'id="appointment-status"'], false)
            ->assertSee('data-document-verification', false)
            ->assertSee('data-document-decision-form', false)
            ->assertSee('Needs checking')
            ->assertDontSee('name="completed_at"', false);
    }

    public function test_document_verification_returns_an_incremental_update_for_the_staff_interface(): void
    {
        $this->travelTo('2026-11-03 09:00:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Mail::fake();
        $requirements = [
            'license' => ['label' => 'Marriage License', 'required' => true],
            'seminar' => ['label' => 'Pre-Cana Seminar', 'required' => true],
        ];
        $appointment = Appointment::factory()->create([
            'status' => 'pending',
            'service_type' => 'wedding',
            'requirements' => $requirements,
            'starts_at' => '2026-11-15 09:00:00',
            'ends_at' => '2026-11-15 10:00:00',
            'reserved_until' => '2026-11-15 10:00:00',
            'documents_due_at' => '2026-11-06 09:00:00',
        ]);

        $response = $this->patchJson('/admin/appointments/'.$appointment->reference_number.'/documents', [
            'type' => 'license',
            'decision' => 'verified',
            'version' => 1,
        ]);

        $response->assertOk()->assertJson([
            'result' => 'verified',
            'message' => 'Physical document verified. Verify the remaining required documents to confirm the appointment.',
            'appointment_status' => 'pending',
            'version' => 2,
            'document' => ['type' => 'license', 'verified' => true],
        ]);
        $this->assertStringContainsString('data-appointment-status-section', $response->json('status_section_html'));
        $appointment->refresh();
        $this->assertSame('pending', $appointment->status);
        $this->assertTrue($appointment->physical_documents['license']['verified']);
        Mail::assertNothingQueued();
    }

    public function test_document_verification_requires_a_clear_staff_decision(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create();

        $response = $this->patchJson('/admin/appointments/'.$appointment->reference_number.'/documents', [
            'type' => array_key_first($appointment->documentRequirements()),
            'version' => 1,
        ]);

        $response->assertInvalid(['decision' => 'Choose Verified or Decline.']);
        $this->assertSame([], $appointment->refresh()->physical_documents ?? []);
    }

    public function test_final_document_verification_returns_confirmed_controls_without_a_page_redirect(): void
    {
        $this->travelTo('2026-11-03 09:00:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Mail::fake();
        $appointment = Appointment::factory()->create([
            'status' => 'pending',
            'requirements' => ['certificate' => ['label' => 'Certificate', 'required' => true]],
            'starts_at' => '2026-11-15 09:00:00',
            'ends_at' => '2026-11-15 10:00:00',
            'reserved_until' => '2026-11-15 10:00:00',
            'documents_due_at' => '2026-11-06 09:00:00',
        ]);

        $response = $this->patchJson('/admin/appointments/'.$appointment->reference_number.'/documents', [
            'type' => 'certificate',
            'decision' => 'verified',
            'version' => 1,
        ]);

        $response->assertOk()->assertJson([
            'result' => 'confirmed',
            'appointment_status' => 'confirmed',
            'version' => 2,
        ]);
        $this->assertStringContainsString('Confirmed', $response->json('status_badge_html'));
        $this->assertStringContainsString('name="completed_at"', $response->json('status_section_html'));
        $this->assertSame('confirmed', $appointment->refresh()->status);
        Mail::assertQueued(AppointmentConfirmed::class, fn (AppointmentConfirmed $mail): bool => $mail->appointment->is($appointment));
    }

    public function test_final_document_verification_does_not_email_a_phone_contact(): void
    {
        $this->travelTo('2026-11-03 09:00:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Mail::fake();
        $appointment = Appointment::factory()->create([
            'status' => 'pending',
            'preferred_contact_method' => 'phone',
            'client_email' => 'optional@example.com',
            'requirements' => ['certificate' => ['label' => 'Certificate', 'required' => true]],
            'starts_at' => '2026-11-15 09:00:00',
            'ends_at' => '2026-11-15 10:00:00',
            'reserved_until' => '2026-11-15 10:00:00',
            'documents_due_at' => '2026-11-06 09:00:00',
        ]);

        $this->patch('/admin/appointments/'.$appointment->reference_number.'/documents', [
            'type' => 'certificate',
            'decision' => 'verified',
            'version' => 1,
        ])->assertSessionHas('status', 'All required physical documents are verified. The appointment is confirmed and now appears on the public parish calendar.');

        $this->assertSame('confirmed', $appointment->refresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_appointment_service_details_open_in_a_modal_instead_of_a_dropdown(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create();

        $this->get('/admin/appointments/'.$appointment->reference_number)
            ->assertOk()
            ->assertSee('data-dialog-target="appointment-service-details"', false)
            ->assertSee('<dialog id="appointment-service-details"', false)
            ->assertSee('View full appointment details')
            ->assertDontSee('<summary class="cursor-pointer text-sm font-semibold">Full service details</summary>', false);
    }

    public function test_priest_can_only_view_assigned_appointments_and_cannot_view_finances(): void
    {
        $this->withoutVite();
        $priest = User::factory()->priest()->withTwoFactor()->create();
        $personnel = Personnel::factory()->create(['user_id' => $priest->id]);
        $assigned = Appointment::factory()->create(['personnel_id' => $personnel->id]);
        $other = Appointment::factory()->create();

        $this->verified($priest)->get('/admin/appointments/'.$assigned->reference_number)->assertSee($assigned->reference_number)->assertDontSee('Payments &amp; balance', false);
        $this->get('/admin/appointments/'.$other->reference_number)->assertNotFound();
        $this->get('/admin/reports')->assertForbidden();
        $this->get('/admin/records')->assertForbidden();
        $this->get('/admin/appointments')->assertDontSee($other->reference_number);
    }

    public function test_staff_can_assign_available_personnel_without_editing_the_confirmed_schedule(): void
    {
        $this->travelTo('2026-10-01 00:00:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $personnel = Personnel::factory()->create();
        $appointment = Appointment::factory()->create([
            'status' => 'confirmed',
            'starts_at' => '2026-10-10 13:00:00',
            'ends_at' => '2026-10-10 14:00:00',
            'reserved_until' => '2026-10-10 14:00:00',
            'confirmed_church' => 'San Isidro Labrador Chapel',
        ]);

        $this->put('/admin/appointments/'.$appointment->reference_number, [
            'personnel_id' => $personnel->id,
            'version' => 1,
            'starts_at' => '2026-10-11T15:00',
            'confirmed_church' => 'St. John Nepomucene Parish',
            'reason' => 'This must be ignored',
        ])->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertSame($personnel->id, $appointment->personnel_id);
        $this->assertSame('2026-10-10 13:00', $appointment->starts_at?->format('Y-m-d H:i'));
        $this->assertSame('San Isidro Labrador Chapel', $appointment->confirmed_church);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.personnel_assigned', 'entity_id' => $appointment->id]);
    }

    public function test_admin_screen_keeps_client_details_and_confirmed_schedule_read_only(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-10-01');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $personnel = Personnel::factory()->create();
        $appointment = Appointment::factory()->create([
            'status' => 'confirmed',
            'preferred_date' => '2026-10-22',
            'preferred_time' => '08:00',
            'starts_at' => '2026-10-22 08:00',
            'ends_at' => '2026-10-22 09:00',
            'reserved_until' => '2026-10-22 09:00',
            'confirmed_church' => 'San Isidro Labrador Chapel',
            'created_at' => '2026-09-28 07:45:00',
        ]);

        $this->get('/admin/appointments/'.$appointment->reference_number)
            ->assertSee('The client-selected service, date, time, and venue were reserved automatically')
            ->assertSee('Appointment created')
            ->assertSee('September 28, 2026 · 7:45 AM')
            ->assertSee('name="personnel_id"', false)
            ->assertDontSee('name="starts_at"', false)
            ->assertDontSee('name="confirmed_church"', false)
            ->assertDontSee('Reason for this schedule')
            ->assertDontSee('Edit contact &amp; participant details', false);

        $this->get('/admin/appointments/'.$appointment->reference_number.'/edit')->assertNotFound();
        $this->patch('/admin/appointments/'.$appointment->reference_number.'/details', [])->assertNotFound();
    }

    public function test_document_buttons_confirm_after_final_verification_or_release_a_declined_hold(): void
    {
        $this->travelTo('2026-10-01');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Mail::fake();
        $requirements = ['license' => ['label' => 'Marriage License', 'required' => true]];
        $verified = Appointment::factory()->create([
            'status' => 'pending', 'service_type' => 'wedding', 'requirements' => $requirements,
            'preferred_date' => '2026-11-15', 'preferred_time' => '09:00',
            'starts_at' => '2026-11-15 09:00', 'ends_at' => '2026-11-15 10:00',
            'reserved_until' => '2026-11-15 10:00',
            'confirmed_church' => 'St. John Nepomucene Parish', 'documents_due_at' => '2026-10-04 00:00',
        ]);
        $declined = Appointment::factory()->create([
            'status' => 'pending', 'service_type' => 'baptism', 'requirements' => $requirements,
            'preferred_date' => '2026-11-16', 'preferred_time' => '09:00',
            'starts_at' => '2026-11-16 09:00', 'ends_at' => '2026-11-16 10:00',
            'reserved_until' => '2026-11-16 10:00',
            'confirmed_church' => 'St. John Nepomucene Parish', 'documents_due_at' => '2026-10-04 00:00',
        ]);

        $this->patch('/admin/appointments/'.$verified->reference_number.'/documents', [
            'type' => array_key_first($verified->documentRequirements()), 'decision' => 'verified', 'version' => 1,
        ])->assertSessionHas('status', 'All required physical documents are verified. The appointment is confirmed and now appears on the public parish calendar.');
        $this->assertSame('confirmed', $verified->refresh()->status);
        $this->assertTrue($verified->physical_documents[array_key_first($verified->documentRequirements())]['verified']);
        Mail::assertQueued(AppointmentConfirmed::class, fn (AppointmentConfirmed $mail): bool => $mail->appointment->is($verified));

        $this->patch('/admin/appointments/'.$declined->reference_number.'/documents', [
            'type' => array_key_first($declined->documentRequirements()), 'decision' => 'declined', 'version' => 1,
        ])->assertSessionHas('status', 'Document declined. The reserved slot was released and will not appear on parish calendars.');
        $this->assertSame('declined', $declined->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.declined', 'entity_id' => $declined->id]);
        $this->get('/admin/appointments?view=calendar&month=2026-11')
            ->assertSee($verified->reference_number)
            ->assertDontSee($declined->reference_number);
        $this->get(route('calendar'))->assertSee('Wedding')->assertDontSee('Baptism');
    }

    public function test_document_verification_expires_an_unverified_reservation_after_its_scheduled_start(): void
    {
        $this->travelTo('2026-10-04 09:00:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Mail::fake();
        $requirements = ['license' => ['label' => 'Marriage License', 'required' => true]];
        $appointment = Appointment::factory()->create([
            'status' => 'pending',
            'service_type' => 'wedding',
            'requirements' => $requirements,
            'starts_at' => '2026-10-04 08:59:00',
            'ends_at' => '2026-10-04 09:59:00',
            'reserved_until' => '2026-10-04 09:59:00',
            'documents_due_at' => '2026-10-04 12:00:00',
        ]);

        $this->patch('/admin/appointments/'.$appointment->reference_number.'/documents', [
            'type' => 'license',
            'decision' => 'verified',
            'version' => 1,
        ])->assertSessionHas(
            'status',
            'The scheduled appointment time has passed. The unverified reservation expired and its slot was released.',
        );

        $appointment->refresh();
        $this->assertSame('expired', $appointment->status);
        $this->assertSame([], $appointment->physical_documents ?? []);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.expired', 'entity_id' => $appointment->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'appointment.confirmed', 'entity_id' => $appointment->id]);
        Mail::assertNothingQueued();
    }

    public function test_pending_appointment_cannot_be_confirmed_before_required_documents_are_verified(): void
    {
        $this->travelTo('2026-10-01');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create([
            'starts_at' => '2026-10-10 10:00',
            'ends_at' => '2026-10-10 11:00',
            'reserved_until' => '2026-10-10 11:00',
            'confirmed_church' => 'San Isidro Labrador Chapel',
            'fee_cents' => 10000,
        ]);

        $this->post('/admin/appointments/'.$appointment->reference_number.'/status', [
            'status' => 'confirmed',
            'version' => 1,
            'reason' => 'Reviewed',
        ])->assertSessionHasErrors([
            'status' => 'Verify every required physical document before confirmation.',
        ]);

        $this->assertSame('pending', $appointment->refresh()->status);
        $this->assertSame(10000, $appointment->balanceCents());
        $this->assertFalse($appointment->physicalDocumentsComplete());
    }

    public function test_status_confirmation_does_not_email_a_phone_contact(): void
    {
        $this->travelTo('2026-10-01');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Mail::fake();
        $appointment = Appointment::factory()->create([
            'preferred_contact_method' => 'phone',
            'client_email' => 'optional@example.com',
            'requirements' => [],
            'starts_at' => '2026-10-10 10:00',
            'ends_at' => '2026-10-10 11:00',
            'reserved_until' => '2026-10-10 11:00',
            'confirmed_church' => 'San Isidro Labrador Chapel',
        ]);

        $this->post('/admin/appointments/'.$appointment->reference_number.'/status', [
            'status' => 'confirmed',
            'version' => 1,
            'reason' => 'Reviewed',
        ])->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $appointment->refresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_confirmation_email_contains_the_confirmed_schedule_and_church(): void
    {
        $appointment = Appointment::factory()->make([
            'reference_number' => 'APT-261014-ABC123',
            'client_full_name' => 'Maria Dela Cruz',
            'starts_at' => '2026-11-20 09:00:00',
            'confirmed_church' => 'San Isidro Labrador Chapel',
        ]);

        $mail = new AppointmentConfirmed($appointment);

        $mail->assertHasSubject('Appointment confirmed: APT-261014-ABC123');
        $mail->assertSeeInHtml('November 20, 2026 at 9:00 AM');
        $mail->assertSeeInHtml('San Isidro Labrador Chapel');
        $mail->assertSeeInText('appointment is confirmed');
    }

    public function test_completion_preserves_unpaid_service_and_rejects_duplicate_finalization(): void
    {
        $this->travelTo('2026-10-10 14:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create(['status' => 'confirmed', 'starts_at' => '2026-10-10 09:00', 'ends_at' => '2026-10-10 10:00', 'confirmed_church' => 'San Isidro Labrador Chapel', 'fee_cents' => 50000]);
        $payload = ['status' => 'completed', 'completed_at' => '2026-10-10T10:00', 'version' => 1, 'reason' => 'Service administered'];

        $this->post('/admin/appointments/'.$appointment->reference_number.'/status', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('service_records', ['appointment_id' => $appointment->id, 'source' => 'operational']);
        $this->assertSame(50000, $appointment->refresh()->balanceCents());
        $payload['version'] = 2;
        $this->post('/admin/appointments/'.$appointment->reference_number.'/status', $payload)->assertSessionHasErrors('status');
        $this->assertDatabaseCount('service_records', 1);
        $this->assertSame('completed', $appointment->refresh()->status);
    }

    public function test_payment_retry_does_not_duplicate_and_refunds_cannot_exceed_original(): void
    {
        $this->travelTo('2026-10-10 14:00');
        $this->verified(User::factory()->staff()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create(['fee_cents' => 10000]);
        $payload = ['idempotency_key' => (string) Str::uuid(), 'kind' => 'payment', 'amount' => '50.25', 'method' => 'cash', 'paid_at' => '2026-10-10T09:00', 'notes' => 'Receipt 100'];
        $url = '/admin/appointments/'.$appointment->reference_number.'/payments';

        $this->post($url, $payload)->assertSessionHasNoErrors();
        $this->post($url, $payload)->assertSessionHasNoErrors();
        $payment = Payment::query()->sole();
        $this->assertSame(5025, $payment->amount_cents);
        $payload = [...$payload, 'idempotency_key' => (string) Str::uuid(), 'kind' => 'refund', 'amount' => '51', 'original_payment_id' => $payment->id];
        $this->post($url, $payload)->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 1);
        $payload['amount'] = '10.25';
        $this->post($url, $payload)->assertSessionHasNoErrors();
        $this->assertSame(6000, $appointment->refresh()->balanceCents());
    }

    public function test_private_document_download_checks_permission_and_audits_access(): void
    {
        Storage::fake('local');
        config(['appointments.document_disk' => 'local']);
        $appointment = Appointment::factory()->create();
        $path = 'appointments/'.$appointment->reference_number.'/certificate.pdf';
        Storage::disk('local')->put($path, 'private document');
        $appointment->update(['documents' => ['birth_certificate' => [['path' => $path, 'original_name' => 'certificate.pdf']]]]);
        $url = '/admin/appointments/'.$appointment->reference_number.'/documents/birth_certificate/0';

        $this->get($url)->assertRedirect('/login');
        $this->verified(User::factory()->admin()->withTwoFactor()->create())->get($url)->assertDownload('certificate.pdf');
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.accessed', 'entity_id' => $appointment->id]);
    }

    public function test_audit_log_cannot_be_updated(): void
    {
        $log = AuditLog::factory()->create();
        $this->expectException(\LogicException::class);

        $log->update(['action' => 'tampered']);
    }

    public function test_optional_office_upload_is_private_audited_and_does_not_verify_physical_copy(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $appointment = Appointment::factory()->create();
        $url = '/admin/appointments/'.$appointment->reference_number.'/documents';
        $data = ['type' => 'birth_certificate', 'files' => [UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf')], 'version' => 1, 'reason' => 'Scanned at the office'];

        $this->verified($admin)->post($url, $data)->assertSessionHasNoErrors();

        $appointment->refresh();
        $file = $appointment->documents['birth_certificate'][0];
        Storage::disk('local')->assertExists($file['path']);
        $this->assertSame($admin->id, $file['uploaded_by']);
        $this->assertFalse($appointment->physicalDocumentsComplete());
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.uploaded', 'entity_id' => $appointment->id]);
        $this->post($url, $data)->assertConflict();
        $this->assertCount(1, Storage::disk('local')->allFiles('appointments'));
        $this->verified(User::factory()->priest()->withTwoFactor()->create())->post($url, [...$data, 'version' => 2])->assertForbidden();
    }

    public function test_confirmation_rechecks_operating_hours_after_settings_change(): void
    {
        $this->travelTo('2026-10-01');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $personnel = Personnel::factory()->create();
        $appointment = Appointment::factory()->create(['requirements' => [], 'starts_at' => '2026-10-10 10:00', 'ends_at' => '2026-10-10 11:00', 'reserved_until' => '2026-10-10 11:30', 'confirmed_church' => 'San Isidro Labrador Chapel', 'personnel_id' => $personnel->id]);
        ParishSetting::query()->where('key', 'office')->update(['value' => ['opens_at' => '12:00']]);

        $this->post('/admin/appointments/'.$appointment->reference_number.'/status', ['status' => 'confirmed', 'version' => 1, 'reason' => 'Reviewed'])->assertSessionHasErrors('starts_at');

        $this->assertSame('pending', $appointment->refresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'appointment.confirmed']);
    }

    public function test_staff_cannot_assign_personnel_or_confirm_at_a_disabled_venue(): void
    {
        $this->travelTo('2026-10-01');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $personnel = Personnel::factory()->create();
        ParishSetting::query()->where('key', 'office')->update([
            'value' => ['active_venues' => ['St. John Nepomucene Parish']],
        ]);
        $appointment = Appointment::factory()->create([
            'requirements' => [],
            'starts_at' => '2026-10-10 10:00',
            'ends_at' => '2026-10-10 11:00',
            'reserved_until' => '2026-10-10 11:30',
            'confirmed_church' => 'San Isidro Labrador Chapel',
            'personnel_id' => null,
        ]);

        $this->put('/admin/appointments/'.$appointment->reference_number, [
            'personnel_id' => $personnel->id,
            'version' => 1,
        ])->assertSessionHasErrors('starts_at');
        $this->post('/admin/appointments/'.$appointment->reference_number.'/status', [
            'status' => 'confirmed',
            'version' => 1,
            'reason' => 'Reviewed',
        ])->assertSessionHasErrors([
            'status' => 'The assigned venue is currently unavailable. Choose another venue before confirmation.',
        ]);

        $this->assertSame('pending', $appointment->refresh()->status);
        $this->assertNull($appointment->personnel_id);
    }

    public function test_staff_calendar_contains_document_holds_and_confirmed_appointments_in_selected_month(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $attributes = ['status' => 'confirmed', 'starts_at' => '2026-11-15 09:00', 'ends_at' => '2026-11-15 10:00', 'reserved_until' => '2026-11-15 10:30'];
        $confirmed = Appointment::factory()->create($attributes);
        $pending = Appointment::factory()->create([...$attributes, 'status' => 'pending']);
        $outside = Appointment::factory()->create([...$attributes, 'starts_at' => '2026-12-15 09:00']);

        $this->get('/admin/appointments?view=calendar&month=2026-11')
            ->assertSee('November 2026')->assertSee($confirmed->reference_number)
            ->assertSee($pending->reference_number)->assertSee('Awaiting documents')
            ->assertDontSee($outside->reference_number);
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }
}
