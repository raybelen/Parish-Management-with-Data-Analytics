<?php

namespace Tests\Feature;

use App\Actions\ReserveAppointmentSlot;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ParishSetting;
use App\Models\Personnel;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Support\OfficeSettings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminCalendarTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_calendar_requires_login_mfa_and_separate_permissions(): void
    {
        $this->get('/admin/calendar')->assertRedirect('/login');
        $this->actingAs(User::factory()->staff()->create())->get('/admin/calendar')->assertRedirect('/admin/security');
        $user = User::factory()->staff()->withTwoFactor()->create();
        $this->verified($user);
        ParishSetting::query()->where('key', 'office')->update(['value' => ['permissions' => ['staff' => ['appointments.view']]]]);
        OfficeSettings::forgetMemoizedValues();

        $this->get('/admin/calendar')->assertForbidden();
        $this->getJson($this->feed())->assertForbidden();
        $this->postJson('/admin/schedules', $this->payload())->assertForbidden();
        $schedule = Schedule::factory()->create();
        $this->putJson('/admin/schedules/'.$schedule->id, [])->assertForbidden();
        $this->deleteJson('/admin/schedules/'.$schedule->id, ['version' => 1])->assertForbidden();
        $this->assertModelExists($schedule);
    }

    public function test_calendar_renders_controls_and_escapes_categories_and_personnel(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Personnel::factory()->create(['name' => '<script>alert(1)</script>']);
        Schedule::factory()->create(['category' => '<img src=x onerror=alert(1)>']);

        $this->get('/admin/calendar')->assertOk()->assertSee('Upcoming Schedules')->assertSee('+ Add Schedule')
            ->assertSee('Year')->assertSee('Month')->assertSee('Week')->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_feed_combines_sources_orders_agenda_and_calculates_current_summaries(): void
    {
        config(['parish.regular_schedules' => []]);
        $this->travelTo('2026-11-02 10:30:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Schedule::factory()->create(['title' => 'Ongoing']);
        Schedule::factory()->create(['title' => 'Later', 'starts_at' => '2026-11-03 09:00', 'ends_at' => '2026-11-03 10:00']);
        Schedule::factory()->create(['title' => 'Finished', 'starts_at' => '2026-11-01 09:00', 'ends_at' => '2026-11-01 10:00']);
        Schedule::factory()->create(['title' => 'Cancelled', 'status' => 'cancelled']);
        Schedule::factory()->create(['title' => 'Private demo', 'source' => 'demo']);
        Appointment::factory()->create(['status' => 'confirmed', 'starts_at' => '2026-11-04 10:00', 'ends_at' => '2026-11-04 11:00']);
        Appointment::factory()->create(['status' => 'pending', 'starts_at' => '2026-11-04 10:00', 'ends_at' => '2026-11-04 11:00']);
        ScheduleBlock::factory()->create(['starts_at' => '2026-11-05 10:00', 'ends_at' => '2026-11-05 11:00']);

        $response = $this->getJson($this->feed())->assertOk()->assertJsonCount(6, 'events')
            ->assertJsonPath('summary', ['month' => 6, 'upcoming' => 3, 'today' => 1, 'completed' => 1])
            ->assertJsonPath('upcoming.0.title', 'Ongoing')->assertJsonPath('upcoming.1.title', 'Later')
            ->assertJsonPath('upcoming_total', 4)->assertJsonMissing(['title' => 'Private demo']);
        $this->assertSame('ongoing', collect($response->json('events'))->firstWhere('title', 'Ongoing')['display_status']);
        $this->assertSame('cancelled', collect($response->json('events'))->firstWhere('title', 'Cancelled')['display_status']);
    }

    public function test_filters_match_search_category_status_personnel_and_overlapping_dates(): void
    {
        config(['parish.regular_schedules' => []]);
        $this->travelTo('2026-11-01');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $person = Personnel::factory()->create();
        Schedule::factory()->create(['title' => 'Choir rehearsal', 'category' => 'Liturgy', 'personnel_id' => $person->id, 'starts_at' => '2026-11-02 23:00', 'ends_at' => '2026-11-03 01:00']);
        Schedule::factory()->create(['title' => 'Different']);

        $this->getJson($this->feed().'&'.http_build_query(['search' => 'CHOIR', 'category' => 'Liturgy', 'status' => 'upcoming', 'personnel_id' => $person->id, 'date_from' => '2026-11-03', 'date_to' => '2026-11-03']))
            ->assertOk()->assertJsonCount(1, 'events')->assertJsonPath('events.0.title', 'Choir rehearsal')->assertJsonPath('upcoming_total', 1);
        $this->getJson($this->feed().'&status=cancelled')->assertJsonCount(0, 'events');
        $this->getJson($this->feed().'&date_to=2026-11-03')->assertOk()->assertJsonCount(2, 'events');
        $this->getJson('/admin/calendar/events?from=2026-01-01&to=2028-01-01')->assertUnprocessable();
        $this->getJson($this->feed().'&date_from=2026-11-04&date_to=2026-11-03')->assertUnprocessable();
    }

    public function test_priest_only_sees_assigned_events_and_cannot_mutate_schedules(): void
    {
        config(['parish.regular_schedules' => []]);
        $this->travelTo('2026-11-01');
        $priest = User::factory()->priest()->withTwoFactor()->create();
        $person = Personnel::factory()->create(['user_id' => $priest->id]);
        Schedule::factory()->create(['personnel_id' => $person->id, 'title' => 'Assigned']);
        $other = Schedule::factory()->create(['title' => 'Other']);

        $this->verified($priest)->getJson($this->feed())->assertOk()->assertJsonCount(1, 'events')->assertJsonPath('events.0.title', 'Assigned')->assertJsonPath('events.0.edit_url', null);
        $this->postJson('/admin/schedules', $this->payload())->assertForbidden();
        $this->putJson('/admin/schedules/'.$other->id, [])->assertForbidden();
        $this->deleteJson('/admin/schedules/'.$other->id, ['version' => 1])->assertForbidden();
    }

    #[DataProvider('recurrences')]
    public function test_recurrence_creates_bounded_occurrences_without_date_drift(string $frequency, string $start, string $end, array $dates): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());

        $this->postJson('/admin/schedules', [...$this->payload(), 'starts_at' => $start.'T10:00', 'ends_at' => $start.'T11:00', 'recurrence' => $frequency, 'recurrence_until' => $end])->assertCreated();

        $this->assertSame($dates, Schedule::query()->orderBy('starts_at')->get()->map(fn (Schedule $schedule): string => $schedule->starts_at->toDateString())->all());
        $this->assertSame(1, Schedule::query()->distinct()->count('series_id'));
        $this->assertSame(count($dates), AuditLog::query()->where('action', 'schedule.created')->count());
    }

    public static function recurrences(): array
    {
        return [
            'daily across years' => ['daily', '2026-12-31', '2027-01-02', ['2026-12-31', '2027-01-01', '2027-01-02']],
            'weekly' => ['weekly', '2026-11-02', '2026-11-16', ['2026-11-02', '2026-11-09', '2026-11-16']],
            'month ends' => ['monthly', '2027-01-31', '2027-03-31', ['2027-01-31', '2027-02-28', '2027-03-31']],
            'leap year' => ['yearly', '2028-02-29', '2030-02-28', ['2028-02-29', '2029-02-28', '2030-02-28']],
        ];
    }

    public function test_series_rolls_back_every_occurrence_and_audit_if_later_occurrence_conflicts(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Schedule::factory()->create(['location' => 'Hall', 'starts_at' => '2026-11-03 10:00', 'ends_at' => '2026-11-03 11:00']);

        $this->postJson('/admin/schedules', [...$this->payload(), 'location' => 'hall', 'recurrence' => 'daily', 'recurrence_until' => '2026-11-04'])
            ->assertUnprocessable()->assertInvalid(['starts_at' => 'Schedule conflict']);

        $this->assertDatabaseCount('schedules', 1);
        $this->assertSame(0, AuditLog::query()->where('action', 'like', 'schedule.%')->count());
    }

    public function test_create_validates_times_personnel_and_recurrence_limits(): void
    {
        $this->travelTo('2026-10-01');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $inactive = Personnel::factory()->create(['active' => false]);

        $this->postJson('/admin/schedules', [])->assertInvalid(['title', 'category', 'starts_at', 'ends_at', 'status', 'recurrence']);
        $this->postJson('/admin/schedules', [...$this->payload(), 'starts_at' => ['invalid']])->assertInvalid('starts_at');
        $this->postJson('/admin/schedules', [...$this->payload(), 'ends_at' => '2026-11-02T09:00'])->assertInvalid('ends_at');
        $this->postJson('/admin/schedules', [...$this->payload(), 'personnel_id' => $inactive->id])->assertInvalid('personnel_id');
        $this->postJson('/admin/schedules', [...$this->payload(), 'status' => 'completed'])->assertInvalid('status');
        $this->postJson('/admin/schedules', [...$this->payload(), 'recurrence' => 'daily'])->assertInvalid('recurrence_until');
        $this->postJson('/admin/schedules', [...$this->payload(), 'recurrence' => 'daily', 'recurrence_until' => '2028-01-01'])->assertInvalid('recurrence_until');
        $this->postJson('/admin/schedules', [...$this->payload(), 'ends_at' => '2027-01-01T10:00'])->assertInvalid('ends_at');
        $this->assertDatabaseEmpty('schedules');
        $this->assertSame(0, AuditLog::query()->where('action', 'like', 'schedule.%')->count());
    }

    public function test_conflicts_include_appointments_blocks_personnel_and_allow_touching_boundaries(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $person = Personnel::factory()->create();
        Appointment::factory()->create(['status' => 'confirmed', 'personnel_id' => $person->id, 'starts_at' => '2026-11-02 09:00', 'ends_at' => '2026-11-02 10:00', 'reserved_until' => '2026-11-02 10:30']);
        $this->postJson('/admin/schedules', [...$this->payload(), 'personnel_id' => $person->id, 'starts_at' => '2026-11-02T09:30'])->assertInvalid('starts_at');
        ScheduleBlock::factory()->create(['church' => 'San Isidro Labrador Chapel', 'starts_at' => '2026-11-02 10:00', 'ends_at' => '2026-11-02 10:30']);
        $this->postJson('/admin/schedules', [...$this->payload(), 'location' => 'San Isidro Labrador Chapel'])->assertInvalid('starts_at');

        $this->postJson('/admin/schedules', [...$this->payload(), 'personnel_id' => $person->id, 'location' => 'San Isidro Labrador Chapel', 'starts_at' => '2026-11-02T10:30'])->assertCreated();
        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_updates_cancel_and_delete_only_one_occurrence_and_reject_stale_edits(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $this->postJson('/admin/schedules', [...$this->payload(), 'recurrence' => 'daily', 'recurrence_until' => '2026-11-03', 'source' => 'demo'])->assertCreated();
        $schedule = Schedule::query()->orderBy('id')->firstOrFail();
        $payload = $this->payload();
        unset($payload['recurrence']);

        $this->putJson('/admin/schedules/'.$schedule->id, [...$payload, 'starts_at' => '2026-11-04T10:00', 'ends_at' => '2026-11-04T11:00', 'version' => 1])->assertOk();
        $this->putJson('/admin/schedules/'.$schedule->id, [...$payload, 'version' => 1])->assertConflict();
        $this->deleteJson('/admin/schedules/'.$schedule->id, ['version' => 1])->assertConflict();
        $this->putJson('/admin/schedules/'.$schedule->id, [...$payload, 'status' => 'cancelled', 'version' => 2])->assertOk();
        $this->assertDatabaseHas('schedules', ['id' => $schedule->id, 'status' => 'cancelled', 'version' => 3, 'source' => 'operational']);
        $this->deleteJson('/admin/schedules/'.$schedule->id, ['version' => 3])->assertOk();

        $this->assertDatabaseCount('schedules', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'schedule.cancelled', 'entity_id' => $schedule->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'schedule.deleted', 'entity_id' => $schedule->id]);
        $otherSource = Schedule::factory()->create(['source' => 'demo']);
        $this->deleteJson('/admin/schedules/'.$otherSource->id, ['version' => 1])->assertNotFound();
    }

    public function test_appointment_and_unavailable_time_cannot_double_book_a_calendar_schedule(): void
    {
        $this->travelTo('2026-10-01');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $person = Personnel::factory()->create();
        Schedule::factory()->create(['location' => 'San Isidro Labrador Chapel', 'personnel_id' => $person->id]);
        $appointment = Appointment::factory()->create();

        $this->put('/admin/appointments/'.$appointment->reference_number, [
            'starts_at' => '2026-11-02T10:00', 'confirmed_church' => 'San Isidro Labrador Chapel', 'personnel_id' => $person->id,
            'duration_minutes' => 60, 'buffer_minutes' => 30, 'reason' => 'Schedule ceremony', 'version' => 1,
        ])->assertSessionHasErrors('starts_at');
        $this->post('/admin/schedule-blocks', ['title' => 'Closed', 'church' => 'San Isidro Labrador Chapel', 'starts_at' => '2026-11-02T10:00', 'ends_at' => '2026-11-02T11:00'])->assertSessionHasErrors('starts_at');
        $this->assertNull($appointment->refresh()->starts_at);
        $this->assertDatabaseEmpty('schedule_blocks');
    }

    public function test_omitting_optional_fields_on_update_still_checks_existing_resource_conflicts(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $schedule = Schedule::factory()->create(['location' => 'Hall']);
        Schedule::factory()->create(['location' => 'Hall', 'starts_at' => '2026-11-03 10:00', 'ends_at' => '2026-11-03 11:00']);
        $payload = $this->payload();
        unset($payload['recurrence']);

        $this->putJson('/admin/schedules/'.$schedule->id, [...$payload, 'starts_at' => '2026-11-03T10:00', 'ends_at' => '2026-11-03T11:00', 'version' => 1])->assertInvalid('starts_at');

        $this->assertSame('2026-11-02', $schedule->refresh()->starts_at->toDateString());
    }

    public function test_regular_mass_is_reserved_and_cancelled_events_release_resources(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        config(['parish.regular_schedules' => [['days' => [1], 'times' => [['value' => '10:00']], 'duration_minutes' => 60, 'buffer_minutes' => 30]]]);
        $this->postJson('/admin/schedules', [...$this->payload(), 'location' => config('parish.churches.0')])->assertInvalid('starts_at');
        Schedule::factory()->create(['status' => 'cancelled', 'location' => 'Hall']);

        $this->postJson('/admin/schedules', [...$this->payload(), 'location' => 'Hall'])->assertCreated();

        $this->assertDatabaseCount('schedules', 2);
    }

    public function test_agenda_paginates_beyond_selected_year_and_feed_includes_cross_year_events(): void
    {
        config(['parish.regular_schedules' => []]);
        $this->travelTo('2026-12-30');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Schedule::factory()->create(['title' => 'Overnight', 'starts_at' => '2026-12-31 23:00', 'ends_at' => '2027-01-01 01:00']);
        Schedule::factory()->count(21)->create(['starts_at' => '2027-01-02 10:00', 'ends_at' => '2027-01-02 11:00']);

        $this->getJson('/admin/calendar/events?from=2027-01-01&to=2028-01-01&page=1')->assertOk()->assertJsonPath('events.0.title', 'Overnight')->assertJsonCount(20, 'upcoming')->assertJsonPath('upcoming_total', 22);
        $this->getJson($this->feed().'&page=2')->assertOk()->assertJsonCount(2, 'upcoming')->assertJsonCount(1, 'events');
    }

    public function test_regular_masses_appear_with_venues_and_read_only_details_for_admins_and_priests(): void
    {
        $this->travelTo('2026-11-07 16:30:00');

        foreach (['admin', 'priest'] as $role) {
            $this->verified(User::factory()->withTwoFactor()->create(['role' => $role]));
            $response = $this->getJson('/admin/calendar/events?from=2026-11-07&to=2026-11-09&category=Mass');

            $response->assertJsonCount(7, 'events')
                ->assertJsonPath('events.0.starts_at', '2026-11-07T07:00')
                ->assertJsonPath('events.1.location', 'Nuestra Señora de los Desamparados')
                ->assertJsonPath('events.1.display_status', 'ongoing')
                ->assertJsonPath('events.2.location', 'San Isidro Labrador Chapel')
                ->assertJsonPath('events.3.starts_at', '2026-11-08T06:00')
                ->assertJsonPath('events.3.location', 'Santa Misericordia Chapel')
                ->assertJsonPath('events.4.starts_at', '2026-11-08T07:30')
                ->assertJsonPath('events.5.location', 'San Fernando Chapel')
                ->assertJsonPath('events.6.starts_at', '2026-11-08T17:00')
                ->assertJsonCount(0, 'upcoming')
                ->assertJsonPath('upcoming_total', 0);
            foreach ($response->json('events') as $event) {
                $this->assertNull($event['edit_url']);
                $this->assertNull($event['detail_url']);
            }
            $this->getJson('/admin/calendar/events?from=2026-11-07&to=2026-11-09&category=Mass&search=San%20Isidro')
                ->assertJsonCount(1, 'events')->assertJsonPath('events.0.starts_at', '2026-11-07T17:00');
        }
        $this->assertDatabaseEmpty('schedules');
    }

    public function test_upcoming_list_keeps_other_services_and_excludes_regular_masses(): void
    {
        $this->travelTo('2026-11-07 12:00:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Appointment::factory()->create([
            'status' => 'confirmed', 'service_type' => 'baptism',
            'starts_at' => '2026-11-08 10:00', 'ends_at' => '2026-11-08 11:00',
        ]);
        Schedule::factory()->create([
            'title' => 'Choir rehearsal', 'starts_at' => '2026-11-08 12:00', 'ends_at' => '2026-11-08 13:00',
        ]);

        $response = $this->getJson('/admin/calendar/events?from=2026-11-07&to=2026-11-09');

        $response->assertJsonCount(9, 'events')->assertJsonCount(2, 'upcoming')
            ->assertJsonPath('upcoming_total', 2)
            ->assertJsonPath('upcoming.0.kind', 'Appointment')
            ->assertJsonPath('upcoming.1.title', 'Choir rehearsal');
    }

    public function test_chapel_mass_and_turnaround_block_only_the_matching_venue(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $payload = [...$this->payload(), 'starts_at' => '2026-11-07T17:00', 'ends_at' => '2026-11-07T17:30', 'location' => 'Nuestra Señora de los Desamparados'];

        $this->postJson('/admin/schedules', $payload)->assertInvalid('starts_at');
        $this->assertDatabaseEmpty('schedules');
        $this->postJson('/admin/schedules', [...$payload, 'location' => 'St. John Nepomucene Parish'])->assertCreated();
        $this->postJson('/admin/schedules', [...$payload, 'starts_at' => '2026-11-07T17:30', 'ends_at' => '2026-11-07T18:00'])->assertCreated();
        $this->assertDatabaseCount('schedules', 2);
    }

    public function test_appointment_reservations_and_admin_scheduling_respect_chapel_masses(): void
    {
        $this->travelTo('2026-11-01');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create([
            'preferred_date' => '2026-11-07', 'preferred_time' => '15:30',
            'preferred_church' => 'Nuestra Señora de los Desamparados',
        ]);

        try {
            app(ReserveAppointmentSlot::class)->handle($appointment);
            $this->fail('A regular Mass conflict must reject the request.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('preferred_time', $exception->errors());
        }
        $this->assertNull($appointment->refresh()->starts_at);
        $this->assertSame('pending', $appointment->status);

        $personnel = Personnel::factory()->create();
        $this->putJson('/admin/appointments/'.$appointment->reference_number, [
            'version' => $appointment->version, 'starts_at' => '2026-11-07T15:30',
            'duration_minutes' => 60, 'buffer_minutes' => 30,
            'confirmed_church' => 'Nuestra Señora de los Desamparados', 'personnel_id' => $personnel->id,
            'reason' => 'Schedule requested ceremony',
        ])->assertInvalid(['starts_at' => 'Mass']);
        $this->assertNull($appointment->refresh()->starts_at);

        $appointment->update(['preferred_church' => 'St. John Nepomucene Parish']);
        $this->assertTrue(app(ReserveAppointmentSlot::class)->handle($appointment));
        $this->assertSame('2026-11-07 15:30', $appointment->refresh()->starts_at->format('Y-m-d H:i'));
        $this->assertSame('pending', $appointment->status);
    }

    private function payload(): array
    {
        return ['title' => 'Parish meeting', 'category' => 'Meeting', 'starts_at' => '2026-11-02T10:00', 'ends_at' => '2026-11-02T11:00', 'status' => 'scheduled', 'recurrence' => 'none'];
    }

    private function feed(): string
    {
        return '/admin/calendar/events?from=2026-01-01&to=2027-01-01';
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }
}
