<?php

namespace Tests\Feature;

use App\Actions\NotifyAppointmentClient;
use App\Jobs\SendAppointmentReminder;
use App\Mail\AppointmentUpdate;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientNotificationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_client_cancellation_queues_one_email_and_rejects_a_repeat(): void
    {
        Mail::fake();
        $appointment = Appointment::factory()->create();
        $url = URL::temporarySignedRoute('appointments.cancellation.store', now()->addMinutes(5), ['appointment' => $appointment]);

        $this->post($url)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url)->assertForbidden();

        $this->assertSame('cancelled', $appointment->refresh()->status);
        Mail::assertQueued(AppointmentUpdate::class, fn (AppointmentUpdate $mail): bool => $mail->event === 'cancelled'
            && $mail->hasTo($appointment->client_email) && $mail->afterCommit === true);
        Mail::assertQueuedCount(1);
    }

    #[DataProvider('officeStatuses')]
    public function test_office_status_change_emails_the_client(string $status): void
    {
        Mail::fake();
        $this->signInOffice();
        $appointment = Appointment::factory()->create();

        $this->post(route('admin.appointments.status', $appointment), [
            'status' => $status, 'version' => 1, 'reason' => 'Private office explanation',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($status, $appointment->refresh()->status);
        Mail::assertQueued(AppointmentUpdate::class, fn (AppointmentUpdate $mail): bool => $mail->event === $status
            && $mail->hasTo($appointment->client_email));
    }

    /** @return array<string, array{string}> */
    public static function officeStatuses(): array
    {
        return ['cancelled' => ['cancelled'], 'declined' => ['declined']];
    }

    public function test_document_decline_emails_the_client(): void
    {
        $this->travelTo('2026-11-07 09:00:00');
        Mail::fake();
        $this->signInOffice();
        $appointment = Appointment::factory()->create([
            'requirements' => ['certificate' => ['label' => 'Certificate', 'required' => true]],
            'documents_due_at' => now()->addDay(), 'starts_at' => now()->addDays(5),
        ]);

        $this->patch(route('admin.documents.update', $appointment), [
            'type' => 'certificate', 'decision' => 'declined', 'version' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('declined', $appointment->refresh()->status);
        Mail::assertQueued(AppointmentUpdate::class, fn (AppointmentUpdate $mail): bool => $mail->event === 'declined');
    }

    public function test_expiration_emails_once_even_when_the_command_runs_again(): void
    {
        $this->travelTo('2026-11-07 09:00:00');
        Mail::fake();
        $appointment = Appointment::factory()->create(['documents_due_at' => now()->subMinute()]);

        $this->artisan('appointments:expire-unverified')->assertSuccessful();
        $this->artisan('appointments:expire-unverified')->assertSuccessful();

        $this->assertSame('expired', $appointment->refresh()->status);
        Mail::assertQueued(AppointmentUpdate::class, fn (AppointmentUpdate $mail): bool => $mail->event === 'expired');
        Mail::assertQueuedCount(1);
    }

    public function test_rescheduling_emails_the_new_schedule_but_unchanged_submission_does_not(): void
    {
        $this->travelTo('2026-09-30 09:00:00');
        Mail::fake();
        $appointment = Appointment::factory()->create([
            'status' => 'confirmed', 'preferred_date' => '2026-10-10', 'preferred_time' => '09:00',
            'starts_at' => '2026-10-10 09:00', 'ends_at' => '2026-10-10 10:00',
            'reserved_until' => '2026-10-10 10:00', 'confirmed_church' => 'St. John Nepomucene Parish',
        ]);
        $url = URL::temporarySignedRoute('appointments.schedule.update', now()->addMinutes(5), ['appointment' => $appointment]);

        $this->patch($url, ['preferred_date' => '2026-11-15', 'preferred_time' => '14:30'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->patch($url, ['preferred_date' => '2026-11-15', 'preferred_time' => '14:30'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('2026-11-15 14:30', $appointment->refresh()->starts_at->format('Y-m-d H:i'));
        Mail::assertQueued(AppointmentUpdate::class, fn (AppointmentUpdate $mail): bool => $mail->event === 'rescheduled'
            && $mail->details['schedule'] === 'November 15, 2026 at 2:30 PM');
        Mail::assertQueuedCount(1);
    }

    public function test_rejected_rescheduling_does_not_email_or_change_the_reservation(): void
    {
        $this->travelTo('2026-09-30 09:00:00');
        Mail::fake();
        $appointment = Appointment::factory()->create([
            'starts_at' => '2026-10-10 10:00', 'ends_at' => '2026-10-10 11:00',
            'reserved_until' => '2026-10-10 11:30', 'confirmed_church' => 'St. John Nepomucene Parish',
        ]);

        $this->patch(URL::temporarySignedRoute('appointments.schedule.update', now()->addMinutes(5), ['appointment' => $appointment]), [
            'preferred_date' => '2026-10-14', 'preferred_time' => '08:00',
        ])->assertSessionHasErrors('preferred_time');

        $this->assertSame('2026-10-10 10:00', $appointment->refresh()->starts_at->format('Y-m-d H:i'));
        Mail::assertNothingOutgoing();
    }

    #[DataProvider('excludedContacts')]
    public function test_lifecycle_emails_respect_contact_preferences_and_exclude_demo_records(array $attributes): void
    {
        Mail::fake();
        $appointment = Appointment::factory()->create($attributes);

        app(NotifyAppointmentClient::class)->handle($appointment, 'cancelled');

        Mail::assertNothingOutgoing();
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function excludedContacts(): array
    {
        return [
            'phone preference' => [['preferred_contact_method' => 'phone']],
            'legacy sms preference' => [['preferred_contact_method' => 'sms']],
            'missing address' => [['client_email' => null]],
            'blank address' => [['client_email' => '']],
            'demo record' => [['source' => 'synthetic']],
        ];
    }

    public function test_scheduler_queues_only_eligible_reminders_with_the_earliest_deadline(): void
    {
        $this->travelTo('2026-11-07 09:00:00');
        Queue::fake([SendAppointmentReminder::class]);
        $upcoming = Appointment::factory()->create(['status' => 'confirmed', 'starts_at' => now()->addDay()]);
        $documents = Appointment::factory()->create(['starts_at' => now()->addHours(6), 'documents_due_at' => now()->addDays(3)]);
        Appointment::factory()->create(['status' => 'confirmed', 'starts_at' => now()->addDay()->addSecond()]);
        Appointment::factory()->create(['status' => 'cancelled', 'starts_at' => now()->addHour()]);
        Appointment::factory()->create(['starts_at' => now()->subMinute(), 'documents_due_at' => now()->addHour()]);
        Appointment::factory()->create(['requirements' => [], 'documents_due_at' => now()->addHour()]);
        Appointment::factory()->create(['preferred_contact_method' => 'phone', 'documents_due_at' => now()->addHour()]);
        Appointment::factory()->create(['source' => 'synthetic', 'documents_due_at' => now()->addHour()]);

        $this->artisan('appointments:send-reminders')->assertSuccessful();

        Queue::assertPushed(SendAppointmentReminder::class, fn (SendAppointmentReminder $job): bool => $job->appointmentId === $upcoming->id
            && $job->event === 'upcoming' && $job->target === '2026-11-08 09:00:00');
        Queue::assertPushed(SendAppointmentReminder::class, fn (SendAppointmentReminder $job): bool => $job->appointmentId === $documents->id
            && $job->event === 'documents' && $job->target === '2026-11-07 15:00:00');
        Queue::assertPushed(SendAppointmentReminder::class, 2);
    }

    public function test_reminders_send_once_per_schedule_and_allow_a_new_schedule(): void
    {
        $this->travelTo('2026-11-07 09:00:00');
        Mail::fake();
        $appointment = Appointment::factory()->create(['status' => 'confirmed', 'starts_at' => now()->addHours(6)]);
        $job = new SendAppointmentReminder($appointment->id, 'upcoming', '2026-11-07 15:00:00');

        $job->handle();
        $job->handle();
        $this->artisan('appointments:send-reminders')->assertSuccessful();

        Mail::assertSent(AppointmentUpdate::class, 1);
        $this->assertSame('2026-11-07 15:00:00', $appointment->refresh()->upcoming_reminder_sent_for->toDateTimeString());
        $appointment->update(['starts_at' => now()->addHours(7)]);
        (new SendAppointmentReminder($appointment->id, 'upcoming', '2026-11-07 16:00:00'))->handle();
        Mail::assertSent(AppointmentUpdate::class, 2);
    }

    public function test_document_reminder_records_the_deadline_and_does_not_repeat(): void
    {
        $this->travelTo('2026-11-07 09:00:00');
        Mail::fake();
        $appointment = Appointment::factory()->create(['documents_due_at' => now()->addHours(6)]);
        $job = new SendAppointmentReminder($appointment->id, 'documents', '2026-11-07 15:00:00');

        $job->handle();
        $job->handle();

        Mail::assertSent(AppointmentUpdate::class, fn (AppointmentUpdate $mail): bool => $mail->event === 'documents'
            && $mail->hasTo($appointment->client_email));
        Mail::assertSentCount(1);
        $this->assertSame('2026-11-07 15:00:00', $appointment->refresh()->documents_reminder_sent_for->toDateTimeString());
    }

    #[DataProvider('staleReminders')]
    public function test_worker_skips_reminders_that_are_no_longer_valid(string $event, array $changes): void
    {
        $this->travelTo('2026-11-07 09:00:00');
        Mail::fake();
        $appointment = Appointment::factory()->create([
            'status' => $event === 'upcoming' ? 'confirmed' : 'pending',
            'starts_at' => now()->addHours(6), 'documents_due_at' => now()->addHours(6),
        ]);
        $job = new SendAppointmentReminder($appointment->id, $event, '2026-11-07 15:00:00');
        $appointment->update($changes);

        $job->handle();

        Mail::assertNothingOutgoing();
        $this->assertNull($appointment->refresh()->{$event.'_reminder_sent_for'});
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function staleReminders(): array
    {
        return [
            'cancelled' => ['upcoming', ['status' => 'cancelled']],
            'completed' => ['upcoming', ['status' => 'completed']],
            'rescheduled' => ['upcoming', ['starts_at' => '2026-11-08 10:00:00']],
            'past start' => ['upcoming', ['starts_at' => '2026-11-07 08:00:00']],
            'changed preference' => ['upcoming', ['preferred_contact_method' => 'phone']],
            'removed email' => ['upcoming', ['client_email' => null]],
            'demo' => ['upcoming', ['source' => 'synthetic']],
            'confirmed documents' => ['documents', ['status' => 'confirmed']],
            'completed documents' => ['documents', ['requirements' => []]],
            'expired documents' => ['documents', ['documents_due_at' => '2026-11-07 08:00:00']],
        ];
    }

    public function test_rolled_back_changes_do_not_enqueue_mail(): void
    {
        config(['queue.default' => 'database']);
        $appointment = Appointment::factory()->create();
        try {
            DB::transaction(function () use ($appointment): void {
                $appointment->update(['status' => 'cancelled']);
                app(NotifyAppointmentClient::class)->handle($appointment, 'cancelled');
                throw new \RuntimeException('Simulated failed change');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame('pending', $appointment->refresh()->status);
        $this->assertDatabaseCount('jobs', 0);
        DB::transaction(fn () => app(NotifyAppointmentClient::class)->handle($appointment, 'rescheduled'));
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_email_preserves_the_event_snapshot_and_excludes_private_notes(): void
    {
        $appointment = Appointment::factory()->make([
            'reference_number' => 'APT-EXAMPLE', 'starts_at' => '2026-11-08 15:00:00',
            'documents_due_at' => '2026-11-10 09:00:00', 'office_notes' => 'Private staff note',
            'outcome' => 'Private office explanation',
        ]);
        $mail = new AppointmentUpdate($appointment, 'rescheduled');
        $appointment->starts_at = '2026-11-09 10:00:00';

        $mail->assertSeeInHtml('November 8, 2026 at 3:00 PM');
        $mail->assertSeeInHtml('not yet confirmed');
        $mail->assertSeeInHtml(route('appointments.lookup.create'));
        $mail->assertDontSeeInHtml('Private staff note');
        $mail->assertDontSeeInHtml('Private office explanation');
        $mail->assertSeeInText('Document verification deadline:');
    }

    public function test_failed_reminder_delivery_can_be_retried_without_marking_it_sent(): void
    {
        $this->travelTo('2026-11-07 09:00:00');
        $originalMailer = Mail::getFacadeRoot();
        $appointment = Appointment::factory()->create(['status' => 'confirmed', 'starts_at' => now()->addHours(6)]);
        $job = new SendAppointmentReminder($appointment->id, 'upcoming', '2026-11-07 15:00:00');
        $pendingMail = \Mockery::mock(PendingMail::class);
        Mail::shouldReceive('to')->once()->with($appointment->client_email)->andReturn($pendingMail);
        $pendingMail->shouldReceive('sendNow')->once()->andThrow(new \RuntimeException('Mail provider unavailable'));

        try {
            $job->handle();
            $this->fail('The provider failure must reach the queue worker for retry.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Mail provider unavailable', $exception->getMessage());
        }

        $this->assertNull($appointment->refresh()->upcoming_reminder_sent_for);
        Mail::swap($originalMailer);
        Mail::fake();
        $job->handle();
        Mail::assertSent(AppointmentUpdate::class, 1);
        $this->assertSame('2026-11-07 15:00:00', $appointment->refresh()->upcoming_reminder_sent_for->toDateTimeString());
    }

    public function test_delayed_worker_does_not_send_a_reminder_after_the_service_starts(): void
    {
        $this->travelTo('2026-11-07 09:00:00');
        Mail::fake();
        $appointment = Appointment::factory()->create(['status' => 'confirmed', 'starts_at' => now()->addHours(6)]);
        $job = new SendAppointmentReminder($appointment->id, 'upcoming', '2026-11-07 15:00:00');
        $this->travelTo('2026-11-07 15:00:00');

        $job->handle();

        Mail::assertNothingOutgoing();
        $this->assertNull($appointment->refresh()->upcoming_reminder_sent_for);
    }

    #[DataProvider('messageEvents')]
    public function test_each_email_renders_html_and_plain_text_with_a_secure_lookup_link(string $event): void
    {
        $appointment = Appointment::factory()->make([
            'reference_number' => 'APT-EXAMPLE', 'client_full_name' => '<script>alert(1)</script>',
            'starts_at' => '2026-11-08 15:00:00', 'documents_due_at' => '2026-11-08 12:00:00',
        ]);
        $mail = new AppointmentUpdate($appointment, $event);

        $mail->assertSeeInHtml('APT-EXAMPLE');
        $mail->assertSeeInHtml(route('appointments.lookup.create'));
        $mail->assertDontSeeInHtml('<script>', false);
        $mail->assertSeeInText('APT-EXAMPLE');
    }

    /** @return array<string, array{string}> */
    public static function messageEvents(): array
    {
        return [
            'service reminder' => ['upcoming'], 'documents reminder' => ['documents'],
            'rescheduled' => ['rescheduled'], 'cancelled' => ['cancelled'],
            'declined' => ['declined'], 'expired' => ['expired'],
        ];
    }

    private function signInOffice(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user)->withSession([
            'admin.mfa_user_id' => $user->id,
            'admin.mfa_fingerprint' => $user->adminSessionFingerprint(),
        ]);
    }
}
