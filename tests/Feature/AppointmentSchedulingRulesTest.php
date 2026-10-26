<?php

namespace Tests\Feature;

use App\Actions\CreateAppointment;
use App\Actions\ManageAppointment;
use App\Actions\ReserveAppointmentSlot;
use App\Models\Appointment;
use App\Models\Personnel;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentSchedulingRulesTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('hours')]
    public function test_approved_hours_and_wedding_start_times(string $service, string $time, bool $available): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        $candidate = Appointment::factory()->make(['service_type' => $service, 'preferred_date' => '2026-11-02', 'preferred_time' => $time]);

        $this->assertSame($available, app(ReserveAppointmentSlot::class)->conflictMessage($candidate) === null);
    }

    public static function hours(): array
    {
        return [
            ['baptism', '07:45', false], ['baptism', '08:00', true], ['baptism', '10:00', true],
            ['baptism', '10:15', false], ['baptism', '11:00', false], ['baptism', '12:45', false],
            ['baptism', '13:00', true], ['funeral', '16:00', false], ['funeral', '16:15', false],
            ['wedding', '08:30', true], ['wedding', '09:30', true], ['wedding', '13:30', true], ['wedding', '14:30', true], ['wedding', '15:30', true], ['wedding', '10:30', false], ['wedding', '16:30', false], ['wedding', '09:15', false],
            ['wedding', '08:00', true], ['wedding', '09:00', true], ['wedding', '10:00', true],
            ['wedding', '13:00', true], ['wedding', '14:00', true], ['wedding', '15:00', true], ['wedding', '16:00', false],
        ];
    }

    #[DataProvider('bookingPairs')]
    public function test_service_periods_and_gaps_in_both_booking_orders(string $firstType, string $firstTime, string $nextType, string $nextTime, bool $sameVenue, bool $available): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        Appointment::factory()->create([
            'service_type' => $firstType, 'starts_at' => '2026-11-02 '.$firstTime,
            'ends_at' => CarbonImmutable::parse('2026-11-02 '.$firstTime)->addHour(),
            'confirmed_church' => 'St. John Nepomucene Parish',
        ]);
        $candidate = Appointment::factory()->make([
            'service_type' => $nextType, 'preferred_date' => '2026-11-02', 'preferred_time' => $nextTime,
            'preferred_church' => $sameVenue ? 'St. John Nepomucene Parish' : 'San Isidro Labrador Chapel',
        ]);

        $this->assertSame($available, app(ReserveAppointmentSlot::class)->conflictMessage($candidate) === null);
    }

    public static function bookingPairs(): array
    {
        return [
            ['wedding', '08:00', 'funeral', '10:00', false, false],
            ['funeral', '10:00', 'wedding', '08:00', false, false],
            ['wedding', '15:00', 'funeral', '13:00', false, false],
            ['funeral', '13:00', 'wedding', '15:00', false, false],
            ['wedding', '08:00', 'funeral', '13:00', true, true],
            ['wedding', '13:00', 'funeral', '08:00', false, true],
            ['baptism', '08:00', 'funeral', '10:00', false, false],
            ['funeral', '08:00', 'baptism', '10:00', false, false],
            ['baptism', '13:00', 'funeral', '08:00', true, true],
            ['wedding', '08:00', 'baptism', '09:00', true, true],
            ['baptism', '09:00', 'wedding', '10:00', true, true],
            ['wedding', '08:00', 'wedding', '08:00', false, true],
            ['wedding', '08:00', 'wedding', '10:00', true, true],
            ['wedding', '08:00', 'wedding', '09:00', true, false],
            ['wedding', '10:00', 'wedding', '09:00', true, false],
            ['wedding', '13:00', 'wedding', '14:00', true, false],
            ['wedding', '15:00', 'wedding', '14:00', true, false],
            ['wedding', '15:00', 'wedding', '16:00', true, false],
            ['wedding', '16:00', 'wedding', '15:00', true, false],
            ['wedding', '13:30', 'wedding', '15:30', true, true],
            ['wedding', '15:30', 'wedding', '13:30', true, true],
            ['wedding', '08:00', 'wedding', '09:00', false, true],
            ['funeral', '13:00', 'funeral', '14:00', true, false],
            ['funeral', '14:00', 'funeral', '13:00', true, false],
            ['funeral', '13:00', 'funeral', '15:00', true, true],
            ['funeral', '13:00', 'funeral', '14:00', false, true],
            ['baptism', '09:00', 'baptism', '09:00', true, false],
        ];
    }

    public function test_shared_baptism_requests_are_rejected_and_ordinary_baptisms_cannot_overlap(): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        $data = Appointment::factory()->make(['preferred_date' => '2026-11-08', 'preferred_time' => '09:00'])->toArray();
        $data['preferred_date'] = '2026-11-08';
        $data['service'] = $data['service_details'];
        try {
            app(CreateAppointment::class)->handle([...$data, 'group_baptism_session_id' => 123]);
            $this->fail('Shared bookings must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('group_baptism_session_id', $exception->errors());
        }
        $this->assertDatabaseEmpty('appointments');
        $first = app(CreateAppointment::class)->handle($data);
        try {
            app(CreateAppointment::class)->handle($data);
            $this->fail('A second baptism must not overlap.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('preferred_time', $exception->errors());
        }
        $this->assertDatabaseCount('appointments', 1);
        $first->update(['status' => 'cancelled']);
        $this->assertNotNull(app(CreateAppointment::class)->handle($data)->starts_at);
    }

    public function test_group_authorization_cannot_be_forged_or_moved_to_another_time(): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        $session = Schedule::factory()->create(['category' => 'Group Baptism', 'location' => 'St. John Nepomucene Parish', 'starts_at' => '2026-11-08 09:00', 'ends_at' => '2026-11-08 10:00']);
        foreach ([['service_type' => 'wedding'], ['preferred_time' => '10:00'], ['preferred_church' => 'San Isidro Labrador Chapel'], ['group_baptism_session_id' => 999999]] as $override) {
            $candidate = Appointment::factory()->make([...['preferred_date' => '2026-11-08', 'preferred_time' => '09:00', 'group_baptism_session_id' => $session->id], ...$override]);
            $this->assertNotNull(app(ReserveAppointmentSlot::class)->conflictMessage($candidate));
        }
        $session->update(['status' => 'cancelled']);
        $candidate = Appointment::factory()->make(['preferred_date' => '2026-11-08', 'preferred_time' => '09:00', 'group_baptism_session_id' => $session->id]);
        $this->assertNotNull(app(ReserveAppointmentSlot::class)->conflictMessage($candidate));
    }

    public function test_staff_cannot_create_shared_sessions_and_existing_history_is_protected(): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        $user = User::factory()->staff()->withTwoFactor()->create();
        $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
        $payload = ['title' => 'Sunday group baptism', 'category' => 'Group Baptism', 'location' => 'St. John Nepomucene Parish', 'starts_at' => '2026-11-08T09:00', 'ends_at' => '2026-11-08T10:00', 'status' => 'scheduled', 'recurrence' => 'none', 'group_capacity' => 5];

        $this->postJson('/admin/schedules', [...$payload, 'starts_at' => '2026-11-07T09:00', 'ends_at' => '2026-11-07T10:00'])->assertInvalid('category');
        $this->postJson('/admin/schedules', $payload)->assertInvalid('category');
        $this->assertDatabaseEmpty('schedules');
        $session = Schedule::factory()->create(['category' => 'Group Baptism']);
        Appointment::factory()->create(['group_baptism_session_id' => $session->id]);
        unset($payload['recurrence']);
        $this->putJson('/admin/schedules/'.$session->id, [...$payload, 'version' => 1, 'status' => 'cancelled'])->assertInvalid('schedule');
        $this->deleteJson('/admin/schedules/'.$session->id, ['version' => 1])->assertInvalid('schedule');
        $this->assertSame('scheduled', $session->refresh()->status);
    }

    public function test_admin_confirmation_rechecks_cross_venue_period_rules(): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        $person = Personnel::factory()->create();
        $funeral = Appointment::factory()->create(['service_type' => 'funeral', 'requirements' => [], 'fee_cents' => 0, 'starts_at' => '2026-11-02 10:00', 'ends_at' => '2026-11-02 11:00', 'reserved_until' => '2026-11-02 11:00', 'personnel_id' => $person->id, 'confirmed_church' => 'St. John Nepomucene Parish']);
        Appointment::factory()->create(['service_type' => 'wedding', 'starts_at' => '2026-11-02 08:00', 'ends_at' => '2026-11-02 09:00', 'confirmed_church' => 'San Isidro Labrador Chapel']);

        try {
            app(ManageAppointment::class)->status($funeral, 'confirmed', $funeral->refresh()->version, 'Checked requirements');
            $this->fail('Confirmation must reject a period conflict.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('different morning or afternoon', $exception->errors()['starts_at'][0]);
        }
        $this->assertSame('pending', $funeral->refresh()->status);
    }

    public function test_live_warnings_include_available_alternatives_when_requested(): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        $this->postJson(route('appointments.availability'), ['service_type' => 'wedding', 'preferred_date' => '2026-11-02', 'preferred_time' => '09:15', 'preferred_church' => 'St. John Nepomucene Parish', 'suggest' => true])
            ->assertJsonPath('available', false)->assertJsonPath('alternatives', [
                ['time' => '08:00', 'label' => '8:00 AM'],
                ['time' => '08:30', 'label' => '8:30 AM'],
                ['time' => '09:00', 'label' => '9:00 AM'],
                ['time' => '09:30', 'label' => '9:30 AM'],
                ['time' => '10:00', 'label' => '10:00 AM'],
                ['time' => '13:00', 'label' => '1:00 PM'],
                ['time' => '13:30', 'label' => '1:30 PM'],
                ['time' => '14:00', 'label' => '2:00 PM'],
                ['time' => '14:30', 'label' => '2:30 PM'],
                ['time' => '15:00', 'label' => '3:00 PM'],
                ['time' => '15:30', 'label' => '3:30 PM'],
            ]);

        Appointment::factory()->create(['service_type' => 'wedding', 'starts_at' => '2026-11-02 09:00', 'ends_at' => '2026-11-02 10:00', 'confirmed_church' => 'St. John Nepomucene Parish']);

        $this->postJson(route('appointments.availability'), ['service_type' => 'wedding', 'preferred_date' => '2026-11-02', 'preferred_time' => '09:00', 'preferred_church' => 'St. John Nepomucene Parish', 'suggest' => true])
            ->assertJsonPath('available', false)->assertJsonPath('alternatives', [
                ['time' => '13:00', 'label' => '1:00 PM'],
                ['time' => '13:30', 'label' => '1:30 PM'],
                ['time' => '14:00', 'label' => '2:00 PM'],
                ['time' => '14:30', 'label' => '2:30 PM'],
                ['time' => '15:00', 'label' => '3:00 PM'],
                ['time' => '15:30', 'label' => '3:30 PM'],
            ]);
    }
}
