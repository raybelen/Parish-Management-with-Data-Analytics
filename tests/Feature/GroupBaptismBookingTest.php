<?php

namespace Tests\Feature;

use App\Actions\ManageAppointment;
use App\Models\Appointment;
use App\Models\ParishSetting;
use App\Models\Personnel;
use App\Models\Schedule;
use App\Models\User;
use App\Support\OfficeSettings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GroupBaptismBookingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_open_future_sessions_are_offered_with_the_existing_baptism_fields(): void
    {
        $this->travelTo('2026-10-01');
        $open = $this->groupSession();
        $this->groupSession(['group_booking_open' => false]);
        $this->groupSession(['status' => 'cancelled']);
        $this->groupSession(['starts_at' => '2026-09-01 09:00', 'ends_at' => '2026-09-01 10:00']);
        $this->groupSession(['source' => 'demo']);

        $this->get(route('appointments.create'))
            ->assertSee('Baptism arrangement')
            ->assertSee('name="service[child_full_name]"', false)
            ->assertSee('name="service[parents_current_residence]"', false)
            ->assertViewHas('groupSessions', fn ($sessions): bool => $sessions->modelKeys() === [$open->id]);
    }

    public function test_multiple_families_share_one_session_without_a_family_limit(): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        $priest = Personnel::factory()->create();
        $session = $this->groupSession(['group_capacity' => 1, 'personnel_id' => $priest->id, 'group_fee' => '250.00']);

        foreach (range(1, 3) as $family) {
            $this->post(route('appointments.store'), $this->payload($session))
                ->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('appointments', 3);
        $this->assertSame(3, Appointment::query()->where('group_baptism_session_id', $session->id)->where('personnel_id', $priest->id)->where('status', 'pending')->where('fee_cents', 25000)->where('payment_status', 'unpaid')->count());
        $this->assertSame(3, Appointment::query()->whereNotNull('documents_due_at')->count());
        $this->postJson(route('appointments.availability'), $this->payload($session))->assertJsonPath('available', true);
        $private = $this->payload($session);
        unset($private['group_baptism_session_id'], $private['baptism_mode']);
        $this->postJson(route('appointments.availability'), $private)->assertJsonPath('available', false);
    }

    #[DataProvider('unavailableSessions')]
    public function test_unavailable_or_mismatched_sessions_cannot_be_booked(array $sessionChanges, array $requestChanges): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        $session = $this->groupSession($sessionChanges);
        $payload = [...$this->payload($session), ...$requestChanges];

        $this->postJson(route('appointments.availability'), $payload)->assertJsonPath('available', false);
        $this->postJson(route('appointments.store'), $payload)->assertUnprocessable();

        $this->assertDatabaseEmpty('appointments');
    }

    public static function unavailableSessions(): array
    {
        return [
            'closed' => [['group_booking_open' => false], []],
            'cancelled' => [['status' => 'cancelled'], []],
            'another source' => [['source' => 'demo'], []],
            'another category' => [['category' => 'Meeting'], []],
            'another time' => [[], ['preferred_time' => '10:00']],
            'another venue' => [[], ['preferred_church' => 'San Isidro Labrador Chapel']],
            'another date' => [[], ['preferred_date' => '2026-11-09']],
            'past session' => [['starts_at' => '2026-09-01 09:00', 'ends_at' => '2026-09-01 10:00'], ['preferred_date' => '2026-09-01']],
        ];
    }

    public function test_admin_can_close_and_reopen_registration_without_disturbing_enrolled_families(): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        $session = $this->groupSession();
        $this->post(route('appointments.store'), $this->payload($session))->assertSessionHasNoErrors();
        $appointment = Appointment::query()->firstOrFail();
        $this->admin();

        $this->putJson(route('admin.schedules.update', $session), [...$this->schedulePayload(), 'group_booking_open' => false, 'version' => 1])
            ->assertOk();

        $this->assertFalse($session->refresh()->group_booking_open);
        $this->assertSame('pending', $appointment->refresh()->status);
        $this->postJson(route('appointments.store'), $this->payload($session))->assertInvalid('group_baptism_session_id');
        $this->assertDatabaseCount('appointments', 1);
        $appointment->update(['requirements' => []]);
        app(ManageAppointment::class)->status($appointment, 'confirmed', $appointment->version, 'Physical documents verified');
        $this->assertSame('confirmed', $appointment->refresh()->status);

        $this->putJson(route('admin.schedules.update', $session), [...$this->schedulePayload(), 'group_booking_open' => true, 'version' => 2])->assertOk();
        $this->post(route('appointments.store'), $this->payload($session))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_admin_opens_an_occasion_session_and_clients_can_book_it(): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        $this->admin();

        $this->postJson(route('admin.schedules.store'), [...$this->schedulePayload(), 'starts_at' => '2026-11-09T09:00', 'ends_at' => '2026-11-09T10:00', 'group_booking_open' => true, 'recurrence' => 'none'])->assertCreated();

        $session = Schedule::query()->firstOrFail();
        $this->assertTrue($session->group_booking_open);
        $this->assertTrue($session->priest_required);
        $this->assertNull($session->group_capacity);
        $this->post(route('appointments.store'), [...$this->payload($session), 'preferred_date' => '2026-11-09'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('appointments', ['group_baptism_session_id' => $session->id]);
    }

    public function test_opening_sessions_preserves_hours_duration_and_appointment_conflicts(): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        $this->admin();
        $payload = [...$this->schedulePayload(), 'group_booking_open' => true, 'recurrence' => 'none'];

        $this->postJson(route('admin.schedules.store'), [...$payload, 'ends_at' => '2026-11-08T11:00'])->assertInvalid('ends_at');
        $this->postJson(route('admin.schedules.store'), [...$payload, 'starts_at' => '2026-11-08T12:00', 'ends_at' => '2026-11-08T13:00'])->assertInvalid('starts_at');
        Appointment::factory()->create(['service_type' => 'funeral', 'starts_at' => '2026-11-08 10:00', 'ends_at' => '2026-11-08 11:00', 'preferred_church' => 'San Isidro Labrador Chapel', 'confirmed_church' => 'San Isidro Labrador Chapel']);
        $this->postJson(route('admin.schedules.store'), $payload)->assertInvalid('starts_at');
        $this->assertDatabaseEmpty('schedules');
    }

    public function test_group_bookings_still_require_baptism_details_and_cannot_be_used_for_other_services(): void
    {
        $this->travelTo('2026-10-01');
        $session = $this->groupSession();
        $payload = $this->payload($session);
        unset($payload['service']['child_full_name']);

        $this->postJson(route('appointments.store'), $payload)->assertInvalid('service.child_full_name');
        $this->postJson(route('appointments.store'), [...$this->payload($session), 'service_type' => 'wedding'])->assertInvalid('baptism_mode');
        $this->postJson(route('appointments.store'), [...$this->payload($session), 'group_baptism_session_id' => null])->assertInvalid('group_baptism_session_id');
        $this->assertDatabaseEmpty('appointments');
    }

    public function test_zero_group_fee_sets_payment_as_not_required(): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        $session = $this->groupSession(['group_fee' => '0.00']);

        $this->post(route('appointments.store'), $this->payload($session))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('appointments', ['fee_cents' => 0, 'payment_status' => 'not_required']);
    }

    public function test_guests_cannot_open_group_sessions(): void
    {
        $this->postJson(route('admin.schedules.store'), [...$this->schedulePayload(), 'group_booking_open' => true, 'recurrence' => 'none'])->assertUnauthorized();
        $this->assertDatabaseEmpty('schedules');
    }

    public function test_priest_unavailability_reassigns_the_session_and_every_active_family_together(): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        $priests = collect([1, 2, 3])->map(fn (int $position): Personnel => Personnel::factory()->rotationPosition($position)->create());
        ParishSetting::query()->where('key', 'office')->update(['value' => ['priest_rotation_anchor_date' => '2026-11-08', 'priest_rotation_anchor_position' => 1]]);
        OfficeSettings::forgetMemoizedValues();
        $session = $this->groupSession(['personnel_id' => $priests[0]->id]);
        Appointment::factory()->count(2)->create(['group_baptism_session_id' => $session->id, 'personnel_id' => $priests[0]->id, 'starts_at' => $session->starts_at, 'ends_at' => $session->ends_at]);
        $this->admin();

        $this->post(route('admin.priests.unavailabilities.store'), ['personnel_id' => $priests[0]->id, 'unavailable_from' => '2026-11-08'])->assertSessionHasNoErrors();

        $this->assertContains($session->refresh()->personnel_id, [$priests[1]->id, $priests[2]->id]);
        $this->assertSame(2, Appointment::query()->where('group_baptism_session_id', $session->id)->where('personnel_id', $session->personnel_id)->count());
        $this->travelTo('2026-11-08 08:00');
        $this->get(route('admin.priests.index'))->assertSee('Group Baptism')->assertDontSee('Schedule conflict');
    }

    public function test_rotation_setup_assigns_the_anchor_group_and_all_its_families_to_one_priest(): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        $priests = collect([1, 2, 3])->map(fn (int $position): Personnel => Personnel::factory()->rotationPosition($position)->create());
        $session = $this->groupSession();
        Appointment::factory()->count(2)->create(['group_baptism_session_id' => $session->id, 'starts_at' => $session->starts_at, 'ends_at' => $session->ends_at]);
        $this->admin();

        $this->put(route('admin.priests.rotation.update'), ['priests' => [1 => $priests[0]->id, 2 => $priests[1]->id, 3 => $priests[2]->id]])->assertSessionHasNoErrors();

        $this->assertSame($priests[0]->id, $session->refresh()->personnel_id);
        $this->assertSame(2, Appointment::query()->where('group_baptism_session_id', $session->id)->where('personnel_id', $priests[0]->id)->count());
    }

    public function test_public_calendar_shows_one_group_service_without_family_information(): void
    {
        $this->travelTo('2026-11-01');
        config(['parish.regular_schedules' => []]);
        $session = $this->groupSession();
        Appointment::factory()->count(2)->create(['status' => 'confirmed', 'client_full_name' => 'Private Family Name', 'group_baptism_session_id' => $session->id, 'starts_at' => $session->starts_at, 'ends_at' => $session->ends_at]);

        $this->get(route('calendar'))->assertSee('Group Baptism')->assertDontSee('Private Family Name')
            ->assertViewHas('calendarMonths', function (array $months): bool {
                $events = collect($months)->flatMap(fn (array $month) => collect($month['weeks'])->flatten(1))->filter()
                    ->flatMap(fn (array $day) => $day['events']);

                return $events->where('name', 'Group Baptism')->count() === 1;
            });
    }

    private function groupSession(array $attributes = []): Schedule
    {
        return Schedule::factory()->create([
            'title' => 'Group baptism', 'category' => 'Group Baptism', 'location' => 'St. John Nepomucene Parish',
            'starts_at' => '2026-11-08 09:00', 'ends_at' => '2026-11-08 10:00', 'group_booking_open' => true, 'priest_required' => true,
            ...$attributes,
        ]);
    }

    private function payload(Schedule $session): array
    {
        $appointment = Appointment::factory()->make();
        $serviceDetails = $appointment->service_details;
        $serviceDetails['child_date_of_birth'] = '2025-01-15';

        return [
            ...$appointment->only(['client_full_name', 'client_contact_number', 'client_address', 'service_type', 'preferred_church', 'payment_option']),
            'preferred_contact_method' => 'phone', 'preferred_date' => '2026-11-08', 'preferred_time' => '09:00',
            'baptism_mode' => 'shared', 'group_baptism_session_id' => $session->id, 'service' => $serviceDetails,
        ];
    }

    private function schedulePayload(): array
    {
        return ['title' => 'Group baptism', 'category' => 'Group Baptism', 'location' => 'St. John Nepomucene Parish', 'starts_at' => '2026-11-08T09:00', 'ends_at' => '2026-11-08T10:00', 'status' => 'scheduled'];
    }

    private function admin(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }
}
