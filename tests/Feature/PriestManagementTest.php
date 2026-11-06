<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ParishSetting;
use App\Models\Personnel;
use App\Models\Schedule;
use App\Models\User;
use App\Support\OfficeSettings;
use App\Support\PriestRotation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PriestManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_can_configure_three_priests_from_the_next_appointment(): void
    {
        $this->travelTo('2026-10-29 08:00:00');
        $this->verified(User::factory()->staff()->withTwoFactor()->create());
        [$priestOne, $priestTwo, $priestThree] = $this->priests();
        $anchor = Appointment::factory()->create([
            'status' => 'confirmed', 'service_type' => 'funeral', 'starts_at' => '2026-10-31 15:00',
            'ends_at' => '2026-10-31 16:00', 'reserved_until' => '2026-10-31 16:00',
        ]);

        $this->put('/admin/priests/rotation', ['priests' => [1 => $priestOne->id, 2 => $priestTwo->id, 3 => $priestThree->id]])
            ->assertRedirectBack()->assertSessionHas('status');

        $this->assertSame($priestOne->id, $anchor->refresh()->personnel_id);
        $settings = ParishSetting::query()->where('key', 'office')->firstOrFail()->value;
        $this->assertSame('2026-10-31', $settings['priest_rotation_anchor_date']);
        OfficeSettings::forgetMemoizedValues();
        $rotation = app(PriestRotation::class);
        $this->assertSame(1, $rotation->regularPosition(now()->toImmutable()->setDate(2026, 10, 31)));
        $this->assertSame(2, $rotation->regularPosition(now()->toImmutable()->setDate(2026, 11, 1)));
        $this->assertSame(3, $rotation->regularPosition(now()->toImmutable()->setDate(2026, 11, 2)));
        config(['parish.regular_schedules' => []]);
        $this->withoutVite();
        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });
        $this->get('/admin/priests')->assertOk()->assertSee('Priest activity schedule')
            ->assertSee('Date')->assertSee('Day')->assertSee('Time')->assertSee('Activity type')
            ->assertSee('October 31, 2026')->assertSee('3:00 PM')->assertSee('Funeral')->assertSee($priestOne->name);
        $this->assertLessThanOrEqual(20, $queryCount);
    }

    public function test_unexpected_activities_balance_all_three_priests_without_changing_regular_rotation(): void
    {
        $this->travelTo('2026-10-29 08:00:00');
        [$priestOne, $priestTwo, $priestThree] = $this->configuredPriests();
        config(['parish.regular_schedules' => [[
            'days' => [7], 'service' => 'Sunday Mass', 'church' => 'St. John Nepomucene Parish',
            'times' => [['value' => '07:00']],
        ]]]);
        $rotation = app(PriestRotation::class);
        $assigned = collect(['10:00', '14:00', '16:00'])->map(function (string $time) use ($rotation): int {
            $start = now()->toImmutable()->setDate(2026, 11, 1)->setTimeFromTimeString($time);
            $appointment = Appointment::factory()->create([
                'status' => 'confirmed', 'starts_at' => $start, 'ends_at' => $start->addHour(), 'reserved_until' => $start->addHour(),
            ]);

            return $rotation->assignAppointment($appointment)->id;
        });

        $this->assertSame([$priestOne->id, $priestThree->id, $priestTwo->id], $assigned->all());
        $this->assertSame(2, $rotation->regularPosition(now()->toImmutable()->setDate(2026, 11, 1)));
    }

    public function test_priest_activity_schedule_is_paginated_into_weekly_periods(): void
    {
        $this->travelTo('2026-11-03 08:00:00');
        $this->withoutVite();
        $this->verified(User::factory()->staff()->withTwoFactor()->create());
        config(['parish.regular_schedules' => []]);
        $firstPeriod = Appointment::factory()->create([
            'status' => 'confirmed',
            'starts_at' => '2026-11-05 09:00:00',
            'ends_at' => '2026-11-05 10:00:00',
            'reserved_until' => '2026-11-05 10:00:00',
        ]);
        $secondPeriod = Appointment::factory()->create([
            'status' => 'confirmed',
            'starts_at' => '2026-11-12 09:00:00',
            'ends_at' => '2026-11-12 10:00:00',
            'reserved_until' => '2026-11-12 10:00:00',
        ]);

        $this->get('/admin/priests')
            ->assertViewHas('scheduleRows', fn ($rows): bool => $rows->pluck('title')->contains($firstPeriod->reference_number)
                && ! $rows->pluck('title')->contains($secondPeriod->reference_number))
            ->assertSee('Period 1 of 5')
            ->assertSee(route('admin.priests.index', ['page' => 2]));
        $this->get('/admin/priests?page=2')
            ->assertViewHas('scheduleRows', fn ($rows): bool => ! $rows->pluck('title')->contains($firstPeriod->reference_number)
                && $rows->pluck('title')->contains($secondPeriod->reference_number))
            ->assertSee('Period 2 of 5');
    }

    public function test_multiple_regular_schedules_on_the_same_day_follow_the_priest_rotation(): void
    {
        $this->travelTo('2026-10-29 08:00:00');
        [$priestOne, $priestTwo, $priestThree] = $this->configuredPriests();
        config(['parish.regular_schedules' => [
            [
                'days' => [7], 'service' => 'Parish Mass', 'church' => 'Parish Church',
                'times' => [['value' => '07:00']],
            ],
            [
                'days' => [7], 'service' => 'Chapel Mass', 'church' => 'San Isidro Chapel',
                'times' => [['value' => '09:00']],
            ],
            [
                'days' => [7], 'service' => 'Community Mass', 'church' => 'San Fernando Chapel',
                'times' => [['value' => '11:00']],
            ],
            [
                'days' => [7], 'service' => 'Evening Mass', 'church' => 'Santa Misericordia Chapel',
                'times' => [['value' => '17:00']],
            ],
        ]]);

        $assignments = app(PriestRotation::class)
            ->regularMassAssignments(now()->toImmutable()->setDate(2026, 11, 1));

        $this->assertSame(
            [$priestTwo->id, $priestThree->id, $priestOne->id, $priestTwo->id],
            collect($assignments)->pluck('regular_personnel_id')->all(),
        );
        $this->assertSame(
            [$priestTwo->id, $priestThree->id, $priestOne->id, $priestTwo->id],
            collect($assignments)->pluck('personnel_id')->all(),
        );
        $this->assertSame(
            ['Parish Church', 'San Isidro Chapel', 'San Fernando Chapel', 'Santa Misericordia Chapel'],
            collect($assignments)->pluck('location')->all(),
        );
    }

    public function test_unavailable_priest_is_replaced_without_an_overlap(): void
    {
        $this->travelTo('2026-10-29 08:00:00');
        $this->verified(User::factory()->staff()->withTwoFactor()->create());
        [$priestOne] = $this->configuredPriests();
        $appointment = Appointment::factory()->create([
            'status' => 'confirmed', 'personnel_id' => $priestOne->id,
            'starts_at' => '2026-11-03 10:00', 'ends_at' => '2026-11-03 11:00', 'reserved_until' => '2026-11-03 11:00',
        ]);

        $this->post('/admin/priests/unavailabilities', [
            'personnel_id' => $priestOne->id, 'unavailable_from' => '2026-11-03', 'reason' => 'Retreat',
        ])->assertRedirectBack()->assertSessionHas('status');

        $this->assertNotSame($priestOne->id, $appointment->refresh()->personnel_id);
        $unavailability = $priestOne->unavailabilities()->firstOrFail();
        $this->assertSame('2026-11-03', $unavailability->unavailable_on->toDateString());
    }

    public function test_staff_can_save_consecutive_priest_unavailability_dates_and_reassign_affected_activities(): void
    {
        $this->travelTo('2026-10-29 08:00:00');
        $this->verified(User::factory()->staff()->withTwoFactor()->create());
        [$priestOne] = $this->configuredPriests();
        $appointments = collect(['2026-11-03', '2026-11-04', '2026-11-05'])->map(fn (string $date): Appointment => Appointment::factory()->create([
            'status' => 'confirmed',
            'personnel_id' => $priestOne->id,
            'starts_at' => "{$date} 10:00",
            'ends_at' => "{$date} 11:00",
            'reserved_until' => "{$date} 11:00",
        ]));
        $schedule = Schedule::factory()->create([
            'personnel_id' => $priestOne->id,
            'priest_required' => true,
            'starts_at' => '2026-11-04 14:00',
            'ends_at' => '2026-11-04 15:00',
        ]);

        $this->post('/admin/priests/unavailabilities', [
            'personnel_id' => $priestOne->id,
            'unavailable_from' => '2026-11-03',
            'unavailable_through' => '2026-11-05',
            'reason' => 'Retreat',
        ])->assertRedirectBack()->assertSessionHas('status', 'Priest unavailability saved for 3 dates and affected activities reassigned where possible.');

        $this->assertSame(
            ['2026-11-03', '2026-11-04', '2026-11-05'],
            $priestOne->unavailabilities()->orderBy('unavailable_on')->get()->map(fn ($unavailability): string => $unavailability->unavailable_on->toDateString())->all(),
        );
        $appointments->each(fn (Appointment $appointment) => $this->assertNotSame($priestOne->id, $appointment->refresh()->personnel_id));
        $this->assertNotSame($priestOne->id, $schedule->refresh()->personnel_id);
    }

    public function test_unavailability_through_date_cannot_be_before_the_start_date(): void
    {
        $this->travelTo('2026-10-29 08:00:00');
        $this->verified(User::factory()->staff()->withTwoFactor()->create());
        [$priestOne] = $this->configuredPriests();

        $this->post('/admin/priests/unavailabilities', [
            'personnel_id' => $priestOne->id,
            'unavailable_from' => '2026-11-05',
            'unavailable_through' => '2026-11-03',
        ])->assertInvalid(['unavailable_through' => 'must be a date after or equal to unavailable from']);

        $this->assertDatabaseCount('priest_unavailabilities', 0);
    }

    public function test_priest_required_calendar_activity_is_one_hour_and_automatically_assigned(): void
    {
        $this->travelTo('2026-10-29 08:00:00');
        $this->verified(User::factory()->staff()->withTwoFactor()->create());
        $this->configuredPriests();
        config(['parish.regular_schedules' => []]);
        $payload = [
            'title' => 'House blessing', 'category' => 'Pastoral visit', 'starts_at' => '2026-11-04T10:00',
            'ends_at' => '2026-11-04T11:00', 'status' => 'scheduled', 'recurrence' => 'none', 'priest_required' => '1',
        ];

        $this->postJson('/admin/schedules', $payload)->assertCreated();
        $this->assertNotNull(Schedule::query()->firstOrFail()->personnel_id);
        $this->postJson('/admin/schedules', [...$payload, 'title' => 'Long activity', 'starts_at' => '2026-11-05T10:00', 'ends_at' => '2026-11-05T11:30'])
            ->assertInvalid(['ends_at' => 'exactly one hour']);
    }

    /** @return array{Personnel, Personnel, Personnel} */
    private function priests(): array
    {
        return [
            Personnel::factory()->rotationPosition(1)->create(['name' => 'Priest 1']),
            Personnel::factory()->rotationPosition(2)->create(['name' => 'Priest 2']),
            Personnel::factory()->rotationPosition(3)->create(['name' => 'Priest 3']),
        ];
    }

    /** @return array{Personnel, Personnel, Personnel} */
    private function configuredPriests(): array
    {
        $priests = $this->priests();
        ParishSetting::query()->where('key', 'office')->update(['value' => [
            'priest_rotation_anchor_date' => '2026-10-31', 'priest_rotation_anchor_position' => 1,
        ]]);
        OfficeSettings::forgetMemoizedValues();

        return $priests;
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession([
            'admin.mfa_user_id' => $user->id,
            'admin.mfa_fingerprint' => $user->adminSessionFingerprint(),
        ]);
    }
}
