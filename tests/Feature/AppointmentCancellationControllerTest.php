<?php

namespace Tests\Feature;

use App\Models\Appointment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentCancellationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_confirmed_appointment_page_uses_a_confirmation_modal_for_cancellation(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-10-01');
        $appointment = Appointment::factory()->create([
            'status' => 'confirmed',
            'starts_at' => '2026-11-15 14:30',
            'ends_at' => '2026-11-15 15:30',
            'confirmed_church' => 'St. John Nepomucene Parish',
        ]);
        $accessToken = 'appointment-modal-access-token';
        $this->withSession(['appointment_access_tokens' => [$accessToken => $appointment->id]]);
        $showUrl = URL::temporarySignedRoute(
            'appointments.show',
            now()->addMinutes(5),
            ['appointment' => $appointment, 'access_token' => $accessToken],
        );

        $response = $this->get($showUrl);

        $response
            ->assertSee('Cancel appointment?')
            ->assertSee('Keep appointment')
            ->assertSee('Yes, cancel appointment')
            ->assertSee('data-dialog-target="cancel-appointment-dialog"', false)
            ->assertSee('data-cancel-confirmation-form', false);
    }

    public function test_confirmed_appointment_can_be_cancelled_and_removed_from_calendar_through_a_signed_link(): void
    {
        $this->travelTo('2026-10-01');
        $appointment = Appointment::factory()->create([
            'status' => 'confirmed',
            'preferred_date' => '2026-11-15',
            'preferred_time' => '14:30',
            'starts_at' => '2026-11-15 14:30',
        ]);

        $response = $this->post($this->signedCancellationUrl($appointment));

        $response->assertRedirect()
            ->assertSessionHas('appointment_message', 'Your appointment has been cancelled and removed from the parish calendar.');
        $appointment->refresh();
        $this->assertSame('cancelled', $appointment->status);
        $this->assertSame('2026-11-15', $appointment->preferred_date->format('Y-m-d'));
        $this->assertSame('14:30', mb_substr($appointment->preferred_time, 0, 5));

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->get($location)
            ->assertSee('Your appointment has been cancelled and removed from the parish calendar.')
            ->assertSee('This appointment is cancelled.')
            ->assertDontSee('Update confirmed schedule');
    }

    public function test_cancellation_rejects_an_unsigned_request(): void
    {
        $appointment = Appointment::factory()->create(['status' => 'pending']);

        $this->post(route('appointments.cancellation.store', $appointment))->assertForbidden();

        $this->assertSame('pending', $appointment->refresh()->status);
    }

    #[DataProvider('closedStatuses')]
    public function test_cancellation_is_forbidden_after_pending_review(string $status): void
    {
        $appointment = Appointment::factory()->create(['status' => $status]);

        $this->post($this->signedCancellationUrl($appointment))->assertForbidden();

        $this->assertSame($status, $appointment->refresh()->status);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function closedStatuses(): array
    {
        return [
            'already cancelled request' => ['cancelled'],
        ];
    }

    private function signedCancellationUrl(Appointment $appointment): string
    {
        return URL::temporarySignedRoute(
            'appointments.cancellation.store',
            now()->addMinutes(5),
            ['appointment' => $appointment],
        );
    }
}
