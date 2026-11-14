<?php

namespace Tests\Feature;

use App\Actions\ReserveAppointmentSlot;
use App\Mail\AppointmentReserved;
use App\Models\Appointment;
use App\Models\Personnel;
use App\Models\User;
use App\Support\PriestRotation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OtherServiceBookingTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('services')]
    public function test_other_service_requires_manual_assignment_and_approval_before_calendar_visibility(string $kind): void
    {
        $this->travelTo('2026-11-01');
        config(['parish.regular_schedules' => []]);
        $priest = Personnel::factory()->create();
        $response = $this->post(route('appointments.store'), $this->payload($kind));
        $response->assertRedirect()->assertSessionHasNoErrors();
        $appointment = Appointment::query()->sole();
        $this->assertSame('pending', $appointment->status);
        $this->assertNull($appointment->personnel_id);
        $this->assertNull($appointment->documents_due_at);
        $this->assertNull($appointment->documentVerificationDeadline());
        $this->assertSame(25050, $appointment->fee_cents);
        $this->get($response->headers->get('Location'))->assertSee('Your request awaits office approval.');
        (new AppointmentReserved($appointment))->assertSeeInHtml('Your service request awaits approval');
        $this->get(route('calendar'))->assertDontSee('Others');

        $this->postJson(route('admin.appointments.status', $appointment), ['status' => 'confirmed', 'version' => $appointment->version, 'reason' => 'Reviewed'])->assertUnauthorized();
        $this->admin();
        $this->get(route('admin.appointments.show', $appointment))->assertSee('Approve request')->assertSee($priest->name);
        $this->getJson(route('admin.calendar.events', ['from' => '2026-11-01', 'to' => '2026-12-01']))->assertJsonCount(0, 'events');
        $this->postJson(route('admin.appointments.status', $appointment), ['status' => 'confirmed', 'version' => $appointment->version, 'reason' => 'Reviewed'])->assertInvalid('personnel_id');
        $this->put(route('admin.appointments.update', $appointment), ['personnel_id' => $priest->id, 'version' => $appointment->version])->assertRedirect()->assertSessionHasNoErrors();
        $appointment->refresh();
        $this->assertSame('pending', $appointment->status);
        $this->post(route('admin.appointments.status', $appointment), ['status' => 'confirmed', 'version' => $appointment->version, 'reason' => 'Request reviewed and approved'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->getJson(route('admin.calendar.events', ['from' => '2026-11-01', 'to' => '2026-12-01']))->assertJsonCount(1, 'events');
        $this->get(route('calendar'))->assertSee('Others')->assertDontSee('Private Service Address');
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.confirmed', 'entity_id' => $appointment->id]);

        app(PriestRotation::class)->assignAppointment($appointment->refresh());
        $this->assertSame($priest->id, $appointment->fresh()->personnel_id);
        $appointment->update(['preferred_time' => '10:00']);
        app(ReserveAppointmentSlot::class)->handle($appointment);
        $this->assertSame('pending', $appointment->fresh()->status);
        $this->assertNull($appointment->fresh()->personnel_id);
        $this->getJson(route('admin.calendar.events', ['from' => '2026-11-01', 'to' => '2026-12-01']))->assertJsonCount(0, 'events');
    }

    public function test_other_service_validates_details_and_renders_both_booking_forms(): void
    {
        $this->travelTo('2026-11-01');
        $this->get(route('appointments.create', ['service' => 'others']))->assertSee('name="service[service_kind]"', false)->assertSee('Anointing of the Sick');
        $this->postJson(route('appointments.store'), [...$this->payload('unknown'), 'service' => ['service_kind' => 'unknown']])
            ->assertInvalid(['service.service_kind', 'service.recipient_full_name', 'service.service_location', 'service.request_details']);
        $this->assertDatabaseCount('appointments', 0);
        $this->admin();
        $this->get(route('admin.appointments.create'))->assertSee('name="service[service_kind]"', false);
        $payload = $this->payload('house_blessing');
        unset($payload['payment_option']);
        $this->post(route('admin.appointments.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('appointments', ['service_type' => 'others', 'status' => 'pending', 'personnel_id' => null]);
    }

    public function test_other_service_uses_its_address_without_reserving_a_church(): void
    {
        $this->travelTo('2026-11-01');
        config(['parish.regular_schedules' => []]);
        Appointment::factory()->create([
            'status' => 'confirmed', 'starts_at' => '2026-11-08 09:00', 'ends_at' => '2026-11-08 10:00',
            'preferred_date' => '2026-11-08', 'preferred_time' => '09:00', 'confirmed_church' => 'St. John Nepomucene Parish',
        ]);
        $payload = $this->payload('house_blessing');
        $this->postJson(route('appointments.availability'), $payload)->assertJsonPath('available', true);
        $this->post(route('appointments.store'), [...$payload, 'preferred_church' => 'St. John Nepomucene Parish'])->assertRedirect()->assertSessionHasNoErrors();
        $appointment = Appointment::query()->where('service_type', 'others')->sole();
        $this->assertSame('Service address provided', $appointment->preferred_church);
        $this->assertSame('Private Service Address', $appointment->service_details['service_location']);
        $this->post(route('appointments.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, Appointment::query()->where('service_type', 'others')->count());
        $response = $this->get(route('appointments.create', ['service' => 'others']));
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//select[@name="preferred_church" and @disabled and not(@required)]')->length);
    }

    public function test_document_verification_cannot_bypass_office_approval(): void
    {
        $this->travelTo('2026-11-01');
        config(['parish.regular_schedules' => []]);
        $this->post(route('appointments.store'), $this->payload('house_blessing'))->assertRedirect();
        $appointment = Appointment::query()->sole();
        $appointment->update(['requirements' => ['supporting_copy' => ['label' => 'Supporting copy', 'required' => true, 'multiple' => false]]]);
        $this->admin();
        $this->patch(route('admin.documents.update', $appointment), ['version' => $appointment->version, 'type' => 'supporting_copy', 'decision' => 'verified'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending', $appointment->fresh()->status);
    }

    public function test_office_cannot_assign_a_busy_priest_or_approve_with_an_inactive_priest(): void
    {
        $this->travelTo('2026-11-01');
        config(['parish.regular_schedules' => []]);
        $priest = Personnel::factory()->create();
        $this->post(route('appointments.store'), $this->payload('house_blessing'))->assertRedirect();
        $appointment = Appointment::query()->sole();
        $other = Appointment::factory()->create([
            'personnel_id' => $priest->id, 'status' => 'confirmed',
            'starts_at' => '2026-11-08 09:00', 'ends_at' => '2026-11-08 10:00',
            'preferred_date' => '2026-11-08', 'preferred_time' => '09:00',
            'preferred_church' => 'San Isidro Labrador Chapel', 'confirmed_church' => 'San Isidro Labrador Chapel',
        ]);
        $this->admin();
        $this->putJson(route('admin.appointments.update', $appointment), ['personnel_id' => $priest->id, 'version' => $appointment->version])->assertInvalid('starts_at');
        $this->assertNull($appointment->fresh()->personnel_id);
        $other->update(['status' => 'cancelled']);
        $this->put(route('admin.appointments.update', $appointment), ['personnel_id' => $priest->id, 'version' => $appointment->version])->assertRedirect()->assertSessionHasNoErrors();
        $priest->update(['active' => false]);
        $this->postJson(route('admin.appointments.status', $appointment), ['status' => 'confirmed', 'version' => $appointment->fresh()->version, 'reason' => 'Reviewed'])->assertInvalid('personnel_id');
        $this->assertSame('pending', $appointment->fresh()->status);
    }

    /** @return array<string, array{string}> */
    public static function services(): array
    {
        return array_combine(['house', 'vehicle', 'item', 'anointing', 'mass'], array_map(fn (string $kind): array => [$kind], ['house_blessing', 'vehicle_blessing', 'religious_item_blessing', 'anointing_of_the_sick', 'celebratory_mass']));
    }

    /** @return array<string, mixed> */
    private function payload(string $kind): array
    {
        return [
            'client_full_name' => 'Ana Santos', 'client_contact_number' => '09171234567', 'client_address' => 'Cebu City',
            'preferred_contact_method' => 'phone', 'service_type' => 'others', 'preferred_date' => '2026-11-08',
            'preferred_time' => '09:00', 'payment_option' => 'walk_in', 'offering_amount' => '250.50',
            'service' => ['service_kind' => $kind, 'recipient_full_name' => 'Ana Santos', 'service_location' => 'Private Service Address', 'request_details' => 'Please arrange the requested service.'],
        ];
    }

    public function test_zero_offering_skips_online_checkout_and_invoice(): void
    {
        $this->travelTo('2026-11-01');
        Http::preventStrayRequests();
        $response = $this->post(route('appointments.store'), [...$this->payload('house_blessing'), 'offering_amount' => '0', 'payment_option' => 'online']);
        $response->assertRedirect()->assertSessionHasNoErrors();
        $appointment = Appointment::query()->sole();
        $this->assertSame(0, $appointment->fee_cents);
        $this->assertSame('not_required', $appointment->payment_status);
        $this->assertSame('pending', $appointment->status);
        $this->assertFalse($appointment->canStartOnlinePayment());
        $this->assertCount(0, $appointment->invoices);
        $this->assertCount(0, $appointment->paymentTransactions);
    }

    #[DataProvider('invalidOfferings')]
    public function test_invalid_offering_is_rejected(mixed $amount, string $paymentOption): void
    {
        $this->travelTo('2026-11-01');
        $this->postJson(route('appointments.store'), [...$this->payload('house_blessing'), 'offering_amount' => $amount, 'payment_option' => $paymentOption])->assertInvalid('offering_amount');
        $this->assertDatabaseCount('appointments', 0);
    }

    /** @return array<string, array{mixed, string}> */
    public static function invalidOfferings(): array
    {
        return [
            'missing' => [null, 'walk_in'],
            'negative' => ['-1', 'walk_in'],
            'too precise' => ['1.001', 'walk_in'],
            'not a number' => ['abc', 'walk_in'],
            'too large' => ['1000000', 'walk_in'],
            'fractional online payment' => ['25.50', 'online'],
        ];
    }

    public function test_online_offering_uses_chosen_amount_for_invoice_and_checkout(): void
    {
        $this->travelTo('2026-11-01');
        URL::forceRootUrl('https://parish.test');
        URL::forceScheme('https');
        config([
            'app.url' => 'https://parish.test',
            'services.xendit.sandbox_enabled' => true,
            'services.xendit.sandbox_secret_key' => 'xnd_development_test_secret',
            'services.xendit.sandbox_webhook_token' => 'sandbox-webhook-token',
            'services.xendit.sandbox_business_id' => 'business-sandbox',
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://api.xendit.co/sessions' => Http::response([
            'payment_session_id' => 'ps-offering',
            'payment_link_url' => 'https://checkout-staging.xendit.co/sessions/ps-offering',
            'business_id' => 'business-sandbox',
            'expires_at' => now()->addMinutes(30)->toIso8601String(),
        ], 201)]);
        $this->post(route('appointments.store'), [...$this->payload('house_blessing'), 'offering_amount' => '275', 'payment_option' => 'online'])
            ->assertRedirect('https://checkout-staging.xendit.co/sessions/ps-offering');
        $appointment = Appointment::query()->sole();
        $this->assertSame(27500, $appointment->fee_cents);
        $this->assertSame(27500, $appointment->invoices()->sole()->amount_cents);
        $this->assertSame(27500, $appointment->paymentTransactions()->sole()->amount_cents);
    }

    private function admin(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }
}
