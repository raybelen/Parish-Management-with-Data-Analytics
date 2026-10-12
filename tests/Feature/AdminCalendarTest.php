<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ParishSetting;
use App\Models\Personnel;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Support\OfficeSettings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

    public function test_conflicts_include_appointment_buffer_blocks_personnel_and_allow_touching_boundaries(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $person = Personnel::factory()->create();
        Appointment::factory()->create(['status' => 'confirmed', 'personnel_id' => $person->id, 'starts_at' => '2026-11-02 09:00', 'ends_at' => '2026-11-02 10:00', 'reserved_until' => '2026-11-02 10:30']);
        $this->postJson('/admin/schedules', [...$this->payload(), 'personnel_id' => $person->id])->assertInvalid('starts_at');
        ScheduleBlock::factory()->create(['church' => 'Parish Chapel', 'starts_at' => '2026-11-02 10:00', 'ends_at' => '2026-11-02 10:30']);
        $this->postJson('/admin/schedules', [...$this->payload(), 'location' => 'Parish Chapel'])->assertInvalid('starts_at');

        $this->postJson('/admin/schedules', [...$this->payload(), 'personnel_id' => $person->id, 'location' => 'Parish Chapel', 'starts_at' => '2026-11-02T10:30'])->assertCreated();
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
        Schedule::factory()->create(['location' => 'Parish Chapel', 'personnel_id' => $person->id]);
        $appointment = Appointment::factory()->create();

        $this->put('/admin/appointments/'.$appointment->reference_number, [
            'starts_at' => '2026-11-02T10:00', 'confirmed_church' => 'Parish Chapel', 'personnel_id' => $person->id,
            'duration_minutes' => 60, 'buffer_minutes' => 30, 'reason' => 'Schedule ceremony', 'version' => 1,
        ])->assertSessionHasErrors('starts_at');
        $this->post('/admin/schedule-blocks', ['title' => 'Closed', 'church' => 'Parish Chapel', 'starts_at' => '2026-11-02T10:00', 'ends_at' => '2026-11-02T11:00'])->assertSessionHasErrors('starts_at');
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
        $this->travelTo('2026-12-30');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Schedule::factory()->create(['title' => 'Overnight', 'starts_at' => '2026-12-31 23:00', 'ends_at' => '2027-01-01 01:00']);
        Schedule::factory()->count(21)->create(['starts_at' => '2027-01-02 10:00', 'ends_at' => '2027-01-02 11:00']);

        $this->getJson('/admin/calendar/events?from=2027-01-01&to=2028-01-01&page=1')->assertOk()->assertJsonPath('events.0.title', 'Overnight')->assertJsonCount(20, 'upcoming')->assertJsonPath('upcoming_total', 22);
        $this->getJson($this->feed().'&page=2')->assertOk()->assertJsonCount(2, 'upcoming')->assertJsonCount(1, 'events');
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
