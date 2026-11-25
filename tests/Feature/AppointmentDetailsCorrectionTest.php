<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AppointmentParticipants;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AppointmentDetailsCorrectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[TestWith(['pending'])]
    #[TestWith(['confirmed'])]
    public function test_staff_can_correct_active_appointments_and_review_the_changes(string $status): void
    {
        $this->withoutVite();
        $staff = $this->staff();
        $appointment = Appointment::factory()->create(['status' => $status, 'client_full_name' => 'Maria Cruz', 'fee_cents' => 10000]);
        AppointmentParticipants::sync($appointment);
        $appointment->refresh();
        $before = Arr::only($appointment->getRawOriginal(), ['status', 'preferred_date', 'preferred_time', 'preferred_church', 'starts_at', 'fee_cents', 'payment_status', 'physical_documents']);
        $oldName = $appointment->service_details['child_full_name'];
        $data = $this->correction($appointment);
        $data['client_contact_number'] = '+63 (917) 123-4567';

        $this->get(route('admin.appointments.edit', $appointment))
            ->assertSee('Maria Cruz')->assertSee('Reason for correction')
            ->assertSee('name="service[child_full_name]"', false)
            ->assertDontSee('name="preferred_date"', false);
        $this->patch(route('admin.appointments.details', $appointment), $data)
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.appointments.show', $appointment));

        $appointment->refresh();
        $this->assertSame($before, Arr::only($appointment->getRawOriginal(), array_keys($before)));
        $this->assertSame('Ana Maria Cruz', $appointment->service_details['child_full_name']);
        $this->assertSame('639171234567', $appointment->client_contact_key);
        $this->assertSame($data['version'] + 1, $appointment->version);
        $this->assertDatabaseHas('appointment_participants', ['appointment_id' => $appointment->id, 'role' => 'child', 'full_name' => 'Ana Maria Cruz']);
        $entry = AuditLog::query()->where('action', 'appointment.details_corrected')->sole();
        $this->assertSame($staff->id, $entry->actor_id);
        $this->assertSame($oldName, $entry->before['service_details.child_full_name']);
        $this->assertSame('Ana Maria Cruz', $entry->after['service_details.child_full_name']);
        $this->assertSame('Corrected using the birth certificate.', $entry->reason);
        $this->get(route('admin.appointments.show', $appointment))
            ->assertSee('View corrections')->assertSee($oldName)->assertSee('Ana Maria Cruz')->assertSee($staff->name);
        $this->get(route('admin.appointments.index', ['q' => 'Ana Maria Cruz']))->assertSee($appointment->reference_number);
    }

    public function test_completion_uses_the_corrected_details_and_participants(): void
    {
        $this->travelTo('2026-11-21 12:00:00');
        $this->staff();
        $appointment = Appointment::factory()->create([
            'status' => 'confirmed', 'preferred_date' => '2026-11-20',
            'starts_at' => '2026-11-20 09:00:00', 'ends_at' => '2026-11-20 10:00:00',
            'confirmed_church' => 'St. John Nepomucene Parish',
        ]);
        $data = $this->correction($appointment);
        $data['service']['child_date_of_birth'] = '2025-12-01';
        $this->patch(route('admin.appointments.details', $appointment), $data)->assertSessionHasNoErrors();

        $this->post(route('admin.appointments.status', $appointment), [
            'status' => 'completed', 'version' => $appointment->fresh()->version,
            'reason' => 'Service completed.', 'completed_at' => '2026-11-20T10:00',
        ])->assertSessionHasNoErrors();

        $record = $appointment->fresh()->serviceRecord;
        $this->assertSame('Ana Maria Cruz', $record->participant_names);
        $this->assertSame('2025-12-01', $record->details['child_date_of_birth']);
        $this->assertSame('Ana Maria Cruz', collect($record->participants)->firstWhere('role', 'child')['full_name']);
    }

    #[TestWith(['completed'])]
    #[TestWith(['cancelled'])]
    #[TestWith(['declined'])]
    #[TestWith(['expired'])]
    #[TestWith(['no_show'])]
    public function test_closed_appointments_cannot_be_edited(string $status): void
    {
        $this->withoutVite();
        $this->staff();
        $appointment = Appointment::factory()->create(['status' => $status]);
        $original = $appointment->service_details;

        $this->get(route('admin.appointments.show', $appointment))->assertDontSee('Edit details');
        $this->get(route('admin.appointments.edit', $appointment))->assertForbidden();
        $this->patch(route('admin.appointments.details', $appointment), $this->correction($appointment))
            ->assertSessionHasErrors(['status' => 'Only pending or confirmed appointments can be corrected.']);

        $this->assertSame($original, $appointment->fresh()->service_details);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'appointment.details_corrected']);
    }

    public function test_stale_forms_cannot_overwrite_a_newer_correction(): void
    {
        $this->staff();
        $appointment = Appointment::factory()->create();
        $data = $this->correction($appointment);
        $appointment->increment('version');

        $this->patch(route('admin.appointments.details', $appointment), $data)
            ->assertSessionHasErrors(['version' => 'This appointment changed since you opened it. Refresh before saving.']);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'appointment.details_corrected']);
        $this->assertSame($appointment->service_details, $appointment->fresh()->service_details);
    }

    public function test_corrections_require_a_reason_document_confirmation_and_valid_service_details(): void
    {
        $this->staff();
        $appointment = Appointment::factory()->create();
        $data = $this->correction($appointment);
        unset($data['change_reason'], $data['documents_checked']);
        $data['service']['child_date_of_birth'] = 'invalid';

        $this->patch(route('admin.appointments.details', $appointment), $data)->assertSessionHasErrors([
            'change_reason' => 'Enter a reason for this correction.',
            'documents_checked' => 'Confirm that the corrected details match the documents before saving.',
            'service.child_date_of_birth',
        ]);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'appointment.details_corrected']);
        $this->assertSame($appointment->service_details, $appointment->fresh()->service_details);
    }

    public function test_schedule_finance_status_and_unknown_service_fields_are_rejected(): void
    {
        $this->staff();
        $appointment = Appointment::factory()->create();
        $data = $this->correction($appointment);
        $data['service']['fee_cents'] = 1;
        $data += ['preferred_date' => '2027-01-01', 'fee_cents' => 1, 'status' => 'completed', 'service_type' => 'wedding'];

        $this->patch(route('admin.appointments.details', $appointment), $data)
            ->assertSessionHasErrors(['preferred_date', 'fee_cents', 'status', 'service_type', 'service']);

        $this->assertSame($appointment->service_details, $appointment->fresh()->service_details);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'appointment.details_corrected']);
    }

    public function test_correction_routes_require_authentication_and_manage_permission(): void
    {
        $this->withoutVite();
        $appointment = Appointment::factory()->create();
        $this->get(route('admin.appointments.edit', $appointment))->assertRedirect('/login');
        $this->patch(route('admin.appointments.details', $appointment), [])->assertRedirect('/login');
        config(['office.permissions.admin' => ['appointments.view']]);
        $this->staff();

        $this->get(route('admin.appointments.show', $appointment))->assertDontSee('Edit details');
        $this->get(route('admin.appointments.edit', $appointment))->assertForbidden();
        $this->patch(route('admin.appointments.details', $appointment), $this->correction($appointment))->assertForbidden();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'appointment.details_corrected']);
    }

    public function test_other_source_appointments_are_not_accessible(): void
    {
        $this->staff();
        $appointment = Appointment::factory()->create(['source' => 'demo']);

        $this->get(route('admin.appointments.edit', $appointment))->assertNotFound();
        $this->patch(route('admin.appointments.details', $appointment), $this->correction($appointment))->assertNotFound();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'appointment.details_corrected']);
    }

    public function test_home_service_corrections_preserve_the_approved_service_and_location(): void
    {
        $this->withoutVite();
        $this->staff();
        $appointment = Appointment::factory()->create(['service_type' => 'others', 'status' => 'confirmed', 'service_details' => [
            'service_kind' => 'house_blessing', 'service_location' => '12 Church Street',
            'recipient_full_name' => 'Maria Cruz', 'request_details' => 'Family blessing',
        ]]);
        $data = $this->correction($appointment);
        $data['service'] = ['recipient_full_name' => 'Ana Cruz', 'request_details' => null];

        $this->get(route('admin.appointments.edit', $appointment))
            ->assertSee('12 Church Street')->assertDontSee('name="service[service_location]"', false);
        $this->patch(route('admin.appointments.details', $appointment), $data)->assertSessionHasNoErrors();

        $this->assertSame([
            'service_kind' => 'house_blessing', 'service_location' => '12 Church Street',
            'recipient_full_name' => 'Ana Cruz', 'request_details' => null,
        ], $appointment->fresh()->service_details);
    }

    #[TestWith(['2026-11-19', true])]
    #[TestWith(['2026-11-21', false])]
    public function test_funeral_death_date_is_checked_against_the_saved_service_date(string $deathDate, bool $valid): void
    {
        $this->travelTo('2026-11-22 12:00:00');
        $this->withoutVite();
        $this->staff();
        $appointment = Appointment::factory()->create([
            'service_type' => 'funeral', 'status' => 'confirmed', 'preferred_date' => '2026-11-20',
            'service_details' => [
                'deceased_full_name' => 'Roberto Santos', 'date_of_death' => '2026-11-18',
                'age' => 80, 'civil_status' => 'widowed', 'residence_address' => '12 Church Street',
                'contact_full_name' => 'Maria Santos', 'contact_relationship' => 'Daughter',
                'anointing_of_sick' => 'yes', 'cause_of_death' => 'As recorded on the death certificate',
                'cemetery_location' => 'Cebu Memorial Park',
            ],
        ]);
        $data = $this->correction($appointment);
        $data['service'] = [...$appointment->service_details, 'date_of_death' => $deathDate];
        $this->get(route('admin.appointments.edit', $appointment))->assertSee('value="2026-11-20"', false);

        $response = $this->patch(route('admin.appointments.details', $appointment), $data);

        if ($valid) {
            $response->assertSessionHasNoErrors();
            $this->assertSame($deathDate, $appointment->fresh()->service_details['date_of_death']);
        } else {
            $response->assertSessionHasErrors('service.date_of_death');
            $this->assertSame('2026-11-18', $appointment->fresh()->service_details['date_of_death']);
        }
    }

    public function test_home_service_location_cannot_be_changed_through_details(): void
    {
        $this->staff();
        $appointment = Appointment::factory()->create(['service_type' => 'others', 'service_details' => [
            'service_kind' => 'house_blessing', 'service_location' => '12 Church Street', 'recipient_full_name' => 'Maria Cruz',
        ]]);
        $data = $this->correction($appointment);
        $data['service'] = ['recipient_full_name' => 'Maria Cruz', 'service_location' => 'Different location'];

        $this->patch(route('admin.appointments.details', $appointment), $data)->assertSessionHasErrors('service');

        $this->assertSame('12 Church Street', $appointment->fresh()->service_details['service_location']);
    }

    public function test_saving_unchanged_details_does_not_create_a_correction(): void
    {
        $this->staff();
        $appointment = Appointment::factory()->create();
        $data = $this->correction($appointment);
        $this->patch(route('admin.appointments.details', $appointment), $data)->assertSessionHasNoErrors();
        $data = $this->correction($appointment);

        $this->patch(route('admin.appointments.details', $appointment), $data)
            ->assertSessionHasErrors(['change_reason' => 'Change at least one detail before saving a correction.']);

        $this->assertSame(1, AuditLog::query()->where('action', 'appointment.details_corrected')->count());
    }

    /** @return array<string, mixed> */
    private function correction(Appointment $appointment): array
    {
        $appointment->refresh();

        return [
            ...$appointment->only(['client_full_name', 'client_contact_number', 'client_email', 'client_address', 'preferred_contact_method', 'additional_notes', 'version']),
            'service' => [...$appointment->service_details, 'child_full_name' => 'Ana Maria Cruz'],
            'change_reason' => 'Corrected using the birth certificate.', 'documents_checked' => '1',
        ];
    }

    private function staff(): User
    {
        $staff = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($staff)->withSession(['admin.mfa_user_id' => $staff->id, 'admin.mfa_fingerprint' => $staff->adminSessionFingerprint()]);

        return $staff;
    }
}
