<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ParishSetting;
use App\Models\Payment;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_scheduling_reserves_preparation_gap_and_blocks_venue_and_personnel_conflicts(): void
    {
        $this->travelTo('2026-10-01 00:00:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $personnel = Personnel::factory()->create();
        Appointment::factory()->create(['status' => 'confirmed', 'personnel_id' => $personnel->id, 'starts_at' => '2026-10-10 10:00:00', 'ends_at' => '2026-10-10 11:00:00', 'reserved_until' => '2026-10-10 11:30:00', 'confirmed_church' => 'Parish Chapel']);
        $appointment = Appointment::factory()->create();
        $payload = $this->schedule($personnel, '2026-10-10T11:15');

        $this->put('/admin/appointments/'.$appointment->reference_number, $payload)->assertSessionHasErrors('starts_at');
        $this->assertNull($appointment->refresh()->starts_at);
        $payload['starts_at'] = '2026-10-10T11:30';
        $this->put('/admin/appointments/'.$appointment->reference_number, $payload)->assertSessionHasNoErrors();

        $this->assertSame('2026-10-10 13:00:00', $appointment->refresh()->reserved_until->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.scheduled', 'entity_id' => $appointment->id]);
        $other = Appointment::factory()->create();
        $payload['starts_at'] = '2026-10-10T10:30';
        $payload['confirmed_church'] = 'St. John Nepomucene Parish Church';
        $this->put('/admin/appointments/'.$other->reference_number, $payload)->assertSessionHasErrors('starts_at');
    }

    public function test_confirmation_requires_physical_copies_and_rechecks_conflicts(): void
    {
        $this->travelTo('2026-10-01');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $personnel = Personnel::factory()->create();
        $appointment = Appointment::factory()->create(['starts_at' => '2026-10-10 10:00', 'ends_at' => '2026-10-10 11:00', 'reserved_until' => '2026-10-10 11:30', 'confirmed_church' => 'Parish Chapel', 'personnel_id' => $personnel->id]);
        $payload = ['status' => 'confirmed', 'version' => 1, 'reason' => 'Reviewed'];

        $this->post('/admin/appointments/'.$appointment->reference_number.'/status', $payload)->assertSessionHasErrors('status');
        $this->patch('/admin/appointments/'.$appointment->reference_number.'/documents', ['type' => 'birth_certificate', 'channel' => 'physical', 'verified' => 1, 'version' => 1, 'reason' => 'Original copy inspected'])->assertSessionHasNoErrors();
        $payload['version'] = 2;
        $this->post('/admin/appointments/'.$appointment->reference_number.'/status', $payload)->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $appointment->refresh()->status);
        $this->assertSame(3, $appointment->version);
    }

    public function test_completion_preserves_unpaid_service_and_rejects_duplicate_finalization(): void
    {
        $this->travelTo('2026-10-10 14:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create(['status' => 'confirmed', 'starts_at' => '2026-10-10 09:00', 'ends_at' => '2026-10-10 10:00', 'confirmed_church' => 'Parish Chapel', 'fee_cents' => 50000]);
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
        $appointment = Appointment::factory()->create(['requirements' => [], 'starts_at' => '2026-10-10 10:00', 'ends_at' => '2026-10-10 11:00', 'reserved_until' => '2026-10-10 11:30', 'confirmed_church' => 'Parish Chapel', 'personnel_id' => $personnel->id]);
        ParishSetting::query()->where('key', 'office')->update(['value' => ['opens_at' => '12:00']]);

        $this->post('/admin/appointments/'.$appointment->reference_number.'/status', ['status' => 'confirmed', 'version' => 1, 'reason' => 'Reviewed'])->assertSessionHasErrors('starts_at');

        $this->assertSame('pending', $appointment->refresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'appointment.confirmed']);
    }

    public function test_calendar_agenda_only_contains_confirmed_appointments_in_selected_month(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $attributes = ['status' => 'confirmed', 'starts_at' => '2026-11-15 09:00', 'ends_at' => '2026-11-15 10:00', 'reserved_until' => '2026-11-15 10:30'];
        $confirmed = Appointment::factory()->create($attributes);
        $pending = Appointment::factory()->create([...$attributes, 'status' => 'pending']);
        $outside = Appointment::factory()->create([...$attributes, 'starts_at' => '2026-12-15 09:00']);

        $this->get('/admin/appointments?view=calendar&month=2026-11')
            ->assertSee('November 2026')->assertSee($confirmed->reference_number)
            ->assertDontSee($pending->reference_number)->assertDontSee($outside->reference_number);
    }

    /** @return array<string, mixed> */
    private function schedule(Personnel $personnel, string $start): array
    {
        return ['starts_at' => $start, 'duration_minutes' => 60, 'buffer_minutes' => 30, 'confirmed_church' => 'Parish Chapel', 'personnel_id' => $personnel->id, 'version' => 1, 'reason' => 'Schedule reviewed'];
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }
}
