<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ParishSetting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentScheduleControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pending_request_schedule_can_be_changed_through_a_signed_link(): void
    {
        $this->travelTo('2026-09-30 09:00:00');
        $appointment = Appointment::factory()->create([
            'status' => 'pending',
            'preferred_date' => '2026-10-10',
            'preferred_time' => '09:00',
        ]);

        $response = $this->patch($this->signedScheduleUrl($appointment), [
            'preferred_date' => '2026-11-15',
            'preferred_time' => '14:30',
        ]);

        $response->assertRedirect()
            ->assertSessionHas('appointment_message', 'Your new date, time, and venue have been reserved. The appointment remains pending document and payment verification.');
        $appointment->refresh();
        $this->assertSame('2026-11-15', $appointment->preferred_date->format('Y-m-d'));
        $this->assertSame('14:30', mb_substr($appointment->preferred_time, 0, 5));
        $this->assertSame('pending', $appointment->status);
        $this->assertSame('2026-11-15 14:30', $appointment->starts_at?->format('Y-m-d H:i'));
        $this->assertSame('2026-11-15 15:30', $appointment->ends_at?->format('Y-m-d H:i'));
        $this->assertSame($appointment->preferred_church, $appointment->confirmed_church);

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->get($location)
            ->assertSee('November 15, 2026')
            ->assertSee('2:30 PM')
            ->assertSee('Update preferred schedule')
            ->assertSee('Reserved schedule');
    }

    public function test_funeral_burial_date_follows_a_changed_mass_schedule(): void
    {
        $this->travelTo('2026-09-30 09:00:00');
        $appointment = Appointment::factory()->create([
            'service_type' => 'funeral',
            'preferred_date' => '2026-10-10',
            'service_details' => ['deceased_full_name' => 'Roberto Santos', 'date_of_death' => '2026-09-25'],
        ]);

        $response = $this->patch($this->signedScheduleUrl($appointment), [
            'preferred_date' => '2026-11-15',
            'preferred_time' => '14:30',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-11-15', $appointment->refresh()->preferred_date->toDateString());
        $this->get($response->headers->get('Location'))
            ->assertSeeInOrder(['Date of burial / funeral Mass', 'November 15, 2026'])
            ->assertSeeInOrder(['Year of death', '2026']);
    }

    public function test_conflicting_schedule_change_releases_the_old_reservation(): void
    {
        $this->travelTo('2026-09-30 09:00:00');
        Appointment::factory()->create([
            'status' => 'pending',
            'starts_at' => '2026-11-15 14:00',
            'ends_at' => '2026-11-15 15:00',
            'reserved_until' => '2026-11-15 15:30',
            'confirmed_church' => 'St. John Nepomucene Parish',
        ]);
        $appointment = Appointment::factory()->create([
            'status' => 'pending',
            'preferred_date' => '2026-10-10',
            'preferred_time' => '09:00',
            'starts_at' => '2026-10-10 09:00',
            'ends_at' => '2026-10-10 10:00',
            'reserved_until' => '2026-10-10 10:30',
            'confirmed_church' => 'St. John Nepomucene Parish',
        ]);

        $response = $this->patch($this->signedScheduleUrl($appointment), [
            'preferred_date' => '2026-11-15',
            'preferred_time' => '14:30',
        ]);

        $response->assertRedirect()->assertSessionHas(
            'appointment_message',
            'Your preferred date and time were updated, but that slot could not be reserved. The parish office will review an alternative schedule.',
        );
        $appointment->refresh();
        $this->assertNull($appointment->starts_at);
        $this->assertNull($appointment->ends_at);
        $this->assertNull($appointment->confirmed_church);
        $this->assertSame('pending', $appointment->status);
    }

    public function test_schedule_change_does_not_reserve_a_disabled_venue(): void
    {
        $this->travelTo('2026-09-30 09:00:00');
        $appointment = Appointment::factory()->create([
            'status' => 'pending',
            'preferred_church' => 'San Isidro Labrador Chapel',
            'starts_at' => '2026-10-10 09:00',
            'ends_at' => '2026-10-10 10:00',
            'reserved_until' => '2026-10-10 10:30',
            'confirmed_church' => 'San Isidro Labrador Chapel',
        ]);
        ParishSetting::query()->where('key', 'office')->update([
            'value' => ['active_venues' => ['St. John Nepomucene Parish']],
        ]);

        $response = $this->patch($this->signedScheduleUrl($appointment), [
            'preferred_date' => '2026-11-15',
            'preferred_time' => '14:30',
        ]);

        $response->assertSessionHas(
            'appointment_message',
            'Your preferred date and time were updated, but that slot could not be reserved. The parish office will review an alternative schedule.',
        );
        $appointment->refresh();
        $this->assertNull($appointment->starts_at);
        $this->assertNull($appointment->confirmed_church);
    }

    public function test_schedule_change_rejects_an_unsigned_request(): void
    {
        $appointment = Appointment::factory()->create([
            'preferred_date' => '2026-10-10',
            'preferred_time' => '09:00',
        ]);

        $this->patch(route('appointments.schedule.update', $appointment), [
            'preferred_date' => '2026-11-15',
            'preferred_time' => '14:30',
        ])->assertForbidden();

        $appointment->refresh();
        $this->assertSame('2026-10-10', $appointment->preferred_date->format('Y-m-d'));
        $this->assertSame('09:00', mb_substr($appointment->preferred_time, 0, 5));
    }

    public function test_schedule_change_rejects_a_past_date(): void
    {
        $this->travelTo('2026-09-30 09:00:00');
        $appointment = Appointment::factory()->create([
            'preferred_date' => '2026-10-10',
            'preferred_time' => '09:00',
        ]);

        $response = $this->patch($this->signedScheduleUrl($appointment), [
            'preferred_date' => '2026-09-29',
            'preferred_time' => '14:30',
        ]);

        $response->assertSessionHasErrors([
            'preferred_date' => 'The preferred date must be today or a future date.',
        ]);
        $appointment->refresh();
        $this->assertSame('2026-10-10', $appointment->preferred_date->format('Y-m-d'));
        $this->assertSame('09:00', mb_substr($appointment->preferred_time, 0, 5));
    }

    #[DataProvider('closedStatuses')]
    public function test_schedule_change_is_forbidden_after_pending_review(string $status): void
    {
        $appointment = Appointment::factory()->create([
            'status' => $status,
            'preferred_date' => '2026-10-10',
            'preferred_time' => '09:00',
        ]);

        $this->patch($this->signedScheduleUrl($appointment), [
            'preferred_date' => '2026-11-15',
            'preferred_time' => '14:30',
        ])->assertForbidden();

        $appointment->refresh();
        $this->assertSame('2026-10-10', $appointment->preferred_date->format('Y-m-d'));
        $this->assertSame('09:00', mb_substr($appointment->preferred_time, 0, 5));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function closedStatuses(): array
    {
        return [
            'confirmed request' => ['confirmed'],
            'cancelled request' => ['cancelled'],
        ];
    }

    private function signedScheduleUrl(Appointment $appointment): string
    {
        return URL::temporarySignedRoute(
            'appointments.schedule.update',
            now()->addMinutes(5),
            ['appointment' => $appointment],
        );
    }
}
