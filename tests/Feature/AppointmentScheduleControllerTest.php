<?php

namespace Tests\Feature;

use App\Models\Appointment;
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
            ->assertSessionHas('appointment_message', 'Your preferred date and time have been updated for parish review.');
        $appointment->refresh();
        $this->assertSame('2026-11-15', $appointment->preferred_date->format('Y-m-d'));
        $this->assertSame('14:30', mb_substr($appointment->preferred_time, 0, 5));
        $this->assertSame('pending', $appointment->status);

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->get($location)
            ->assertSee('November 15, 2026')
            ->assertSee('2:30 PM')
            ->assertSee('Update preferred schedule');
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
