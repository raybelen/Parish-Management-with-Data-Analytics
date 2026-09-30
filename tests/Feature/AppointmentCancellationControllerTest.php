<?php

namespace Tests\Feature;

use App\Models\Appointment;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentCancellationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pending_request_page_uses_a_confirmation_modal_for_cancellation(): void
    {
        $this->withoutVite();
        $appointment = Appointment::factory()->create(['status' => 'pending']);
        $showUrl = URL::temporarySignedRoute(
            'appointments.show',
            now()->addMinutes(5),
            ['appointment' => $appointment],
        );

        $response = $this->get($showUrl);

        $response
            ->assertSee('Cancel appointment request?')
            ->assertSee('Keep appointment')
            ->assertSee('Yes, cancel request');
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//button[@data-dialog-target="cancel-appointment-dialog" and @type="button"]')->length);
        $this->assertSame(1, $xpath->query('//dialog[@id="cancel-appointment-dialog"]//form[@data-cancel-confirmation-form]')->length);
    }

    public function test_pending_request_can_be_cancelled_through_a_signed_link(): void
    {
        $appointment = Appointment::factory()->create([
            'status' => 'pending',
            'preferred_date' => '2026-11-15',
            'preferred_time' => '14:30',
        ]);

        $response = $this->post($this->signedCancellationUrl($appointment));

        $response->assertRedirect()
            ->assertSessionHas('appointment_message', 'Your appointment request has been cancelled.');
        $appointment->refresh();
        $this->assertSame('cancelled', $appointment->status);
        $this->assertSame('2026-11-15', $appointment->preferred_date->format('Y-m-d'));
        $this->assertSame('14:30', mb_substr($appointment->preferred_time, 0, 5));

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->get($location)
            ->assertSee('Your appointment request has been cancelled.')
            ->assertSee('This appointment request is cancelled.')
            ->assertDontSee('Update preferred schedule');
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
            'confirmed request' => ['confirmed'],
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
