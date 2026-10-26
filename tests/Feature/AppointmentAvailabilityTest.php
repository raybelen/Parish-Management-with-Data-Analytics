<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Models\ServiceType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AppointmentAvailabilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_all_services_report_mass_conflicts_without_creating_requests(): void
    {
        $this->travelTo('2026-10-01');
        foreach (ServiceType::query()->pluck('slug') as $service) {
            $this->postJson(route('appointments.availability'), [...$this->payload(), 'service_type' => $service, 'preferred_time' => '07:00'])
                ->assertOk()->assertJsonPath('available', false)->assertJsonStructure(['message']);
            $this->postJson(route('appointments.availability'), [...$this->payload(), 'service_type' => $service, 'preferred_church' => 'San Isidro Labrador Chapel'])
                ->assertOk()->assertJsonPath('available', true);
        }
        $this->assertDatabaseEmpty('appointments');
    }

    public function test_existing_reservations_blocks_and_schedules_report_private_conflicts(): void
    {
        $this->travelTo('2026-10-01');
        Appointment::factory()->create(['client_full_name' => 'Private Client', 'status' => 'pending', 'starts_at' => '2026-11-02 10:00', 'ends_at' => '2026-11-02 11:00', 'reserved_until' => '2026-11-02 11:30', 'confirmed_church' => 'St. John Nepomucene Parish']);
        ScheduleBlock::factory()->create(['church' => 'St. John Nepomucene Parish', 'starts_at' => '2026-11-03 10:00', 'ends_at' => '2026-11-03 12:00']);
        Schedule::factory()->create(['location' => 'St. John Nepomucene Parish', 'starts_at' => '2026-11-04 10:00', 'ends_at' => '2026-11-04 12:00']);

        foreach (['2026-11-02', '2026-11-03', '2026-11-04'] as $date) {
            $this->postJson(route('appointments.availability'), [...$this->payload(), 'preferred_date' => $date, 'preferred_time' => '11:00'])
                ->assertOk()->assertJsonPath('available', false)->assertDontSee('Private Client');
        }
        $this->postJson(route('appointments.availability'), [...$this->payload(), 'preferred_time' => '13:00'])
            ->assertJsonPath('available', true)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_invalid_input_and_closed_hours_are_rejected(): void
    {
        $this->travelTo('2026-10-01');
        $this->postJson(route('appointments.availability'), [])->assertUnprocessable()->assertInvalid(['preferred_date', 'preferred_time', 'preferred_church', 'service_type']);
        $this->postJson(route('appointments.availability'), [...$this->payload(), 'preferred_time' => '23:00'])->assertJsonPath('available', false);
        $this->postJson(route('appointments.availability'), [...$this->payload(), 'preferred_date' => '2026-09-01'])->assertJsonPath('available', false);
    }

    public function test_signed_reschedule_check_excludes_self_and_cannot_override_venue(): void
    {
        $this->travelTo('2026-10-01');
        $appointment = Appointment::factory()->create(['preferred_date' => '2026-11-02', 'preferred_time' => '10:00', 'starts_at' => '2026-11-02 10:00', 'ends_at' => '2026-11-02 11:00', 'reserved_until' => '2026-11-02 11:30', 'confirmed_church' => 'St. John Nepomucene Parish']);
        $url = URL::temporarySignedRoute('appointments.availability.update', now()->addMinutes(30), ['appointment' => $appointment]);

        $this->postJson(route('appointments.availability.update', $appointment), $this->payload())->assertForbidden();
        $this->postJson($url, $this->payload())->assertJsonPath('available', true);
        $this->postJson($url, [...$this->payload(), 'preferred_time' => '07:00', 'preferred_church' => 'San Isidro Labrador Chapel'])->assertJsonPath('available', false);
        $this->assertSame('10:00', substr($appointment->refresh()->preferred_time, 0, 5));
        $appointment->update(['status' => 'confirmed']);
        $this->postJson($url, $this->payload())->assertForbidden();
    }

    public function test_all_services_require_thirty_minutes_before_mass_at_the_selected_venue(): void
    {
        $this->travelTo('2026-10-01');

        foreach (ServiceType::query()->pluck('slug') as $service) {
            foreach ([['2026-11-08', '08:00', 'San Fernando Chapel'], ['2026-11-07', '15:00', 'Nuestra Señora de los Desamparados']] as [$date, $time, $venue]) {
                $this->postJson(route('appointments.availability'), [...$this->payload(), 'service_type' => $service, 'preferred_date' => $date, 'preferred_time' => $time, 'preferred_church' => $venue])
                    ->assertJsonPath('available', false)
                    ->assertJsonPath('message', 'Your appointment must end at least 30 minutes before Mass. Please choose an earlier time or another venue.');
            }
            $this->postJson(route('appointments.availability'), [...$this->payload(), 'service_type' => $service, 'preferred_date' => '2026-11-04', 'preferred_time' => '15:30'])
                ->assertJsonPath('available', true);
            $this->postJson(route('appointments.availability'), [...$this->payload(), 'service_type' => $service, 'preferred_date' => '2026-11-04', 'preferred_time' => '15:30', 'preferred_church' => 'San Isidro Labrador Chapel'])
                ->assertJsonPath('available', true);
        }
        $this->postJson(route('appointments.availability'), [...$this->payload(), 'preferred_date' => '2026-11-04', 'preferred_time' => '15:31'])
            ->assertJsonPath('available', false);
        $this->assertDatabaseEmpty('appointments');
    }

    public function test_latest_start_is_three_thirty_for_all_services_and_venues(): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);

        foreach (ServiceType::query()->pluck('slug') as $service) {
            foreach (config('parish.churches') as $venue) {
                foreach (['15:30' => true, '15:31' => false, '16:00' => false] as $time => $available) {
                    $this->postJson(route('appointments.availability'), [...$this->payload(), 'service_type' => $service, 'preferred_church' => $venue, 'preferred_time' => $time])
                        ->assertJsonPath('available', $available);
                }
            }
        }
    }

    public function test_shared_baptism_controls_and_requests_are_disabled(): void
    {
        $this->travelTo('2026-10-01');
        $this->get(route('appointments.create'))->assertDontSee('Shared Sunday baptism')->assertDontSee('Baptism arrangement');
        $this->postJson(route('appointments.availability'), [...$this->payload(), 'baptism_mode' => 'shared', 'group_baptism_session_id' => 1])
            ->assertInvalid(['baptism_mode', 'group_baptism_session_id']);
        $this->postJson(route('appointments.store'), [...$this->payload(), 'baptism_mode' => 'shared', 'group_baptism_session_id' => 1])
            ->assertInvalid(['baptism_mode', 'group_baptism_session_id']);
    }

    private function payload(): array
    {
        return ['service_type' => 'baptism', 'preferred_date' => '2026-11-02', 'preferred_time' => '10:00', 'preferred_church' => 'St. John Nepomucene Parish'];
    }
}
