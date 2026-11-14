<?php

namespace Tests\Feature;

use App\Mail\FacilityBookingUpdate;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class OnlineFacilityBookingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_email_verification_is_required_before_a_request_holds_the_time(): void
    {
        Mail::fake();
        $this->withoutVite();
        $facility = Facility::factory()->create();
        $data = $this->bookingData();

        $this->post(route('facilities.bookings.store', $facility), $data)->assertSessionHasNoErrors()->assertRedirect(route('facilities.index'));

        $booking = FacilityBooking::query()->sole();
        $this->assertSame('unverified', $booking->status);
        $this->assertNotNull($booking->expires_at);
        Mail::assertQueued(FacilityBookingUpdate::class, fn (FacilityBookingUpdate $mail): bool => $mail->event === 'verification' && $mail->details['reference'] === $booking->reference);

        $this->post(route('facilities.bookings.store', $facility), [...$data, 'contact_email' => 'second@example.com'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('facility_bookings', 2);

        $verificationUrl = URL::temporarySignedRoute('facilities.bookings.verify', now()->addMinutes(30), ['booking' => $booking->reference]);
        $this->get($verificationUrl)->assertRedirect();
        $this->assertSame('pending', $booking->refresh()->status);
        $this->assertTrue($booking->expires_at->between(now()->addHours(23), now()->addHours(25)));
        $this->post(route('facilities.bookings.store', $facility), [...$data, 'contact_email' => 'third@example.com'])->assertSessionHasErrors('starts_at');
    }

    public function test_staff_approval_and_cancellation_notify_the_requester(): void
    {
        Mail::fake();
        $booking = FacilityBooking::factory()->create([
            'status' => 'pending',
            'reference' => 'FAC-APPROVAL1234',
            'contact_email' => 'council@example.com',
            'expires_at' => now()->addDay(),
        ]);
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $this->verified($admin);

        $this->patch(route('admin.facilities.bookings.update', [$booking->facility, $booking]))->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $booking->refresh()->status);
        $this->assertNull($booking->expires_at);
        Mail::assertQueued(FacilityBookingUpdate::class, fn (FacilityBookingUpdate $mail): bool => $mail->event === 'approved');

        $this->delete(route('admin.facilities.bookings.destroy', [$booking->facility, $booking]), ['reason' => 'Parish activity has priority'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $booking->refresh()->status);
        Mail::assertQueued(FacilityBookingUpdate::class, fn (FacilityBookingUpdate $mail): bool => $mail->event === 'cancelled' && $mail->details['reason'] === 'Parish activity has priority');
    }

    public function test_requester_can_recover_view_and_cancel_a_booking_with_signed_links(): void
    {
        Mail::fake();
        $this->withoutVite();
        $booking = FacilityBooking::factory()->create([
            'reference' => 'FAC-RECOVER12345',
            'contact_email' => 'owner@example.com',
        ]);

        $this->post(route('facilities.bookings.recover'), ['reference' => strtolower($booking->reference), 'contact_email' => 'OWNER@example.com'])
            ->assertSessionHas('status', 'If those details match a request, a secure access link has been emailed.');
        Mail::assertQueued(FacilityBookingUpdate::class, fn (FacilityBookingUpdate $mail): bool => $mail->event === 'access');
        $this->get(route('facilities.bookings.show', $booking->reference))->assertForbidden();

        $viewUrl = URL::temporarySignedRoute('facilities.bookings.show', now()->addHour(), ['booking' => $booking->reference]);
        $this->get($viewUrl)->assertSee($booking->reference)->assertSee('Approved');
        $this->delete(route('facilities.bookings.destroy', $booking->reference), ['reason' => 'Unsigned'])->assertForbidden();
        $cancelUrl = URL::temporarySignedRoute('facilities.bookings.destroy', now()->addHour(), ['booking' => $booking->reference]);
        $this->delete($cancelUrl, ['reason' => 'Plans changed'])->assertRedirect(route('facilities.index'));
        $this->assertSame('cancelled', $booking->refresh()->status);
        $this->assertSame('Plans changed', $booking->cancellation_reason);
    }

    public function test_expiration_releases_held_time_and_notifies_the_requester(): void
    {
        Mail::fake();
        $this->travelTo('2026-11-14 08:00:00');
        $facility = Facility::factory()->create();
        $booking = FacilityBooking::factory()->create([
            'facility_id' => $facility,
            'status' => 'pending',
            'reference' => 'FAC-EXPIRED12345',
            'contact_email' => 'owner@example.com',
            'starts_at' => now()->addDay()->setTime(9, 0),
            'ends_at' => now()->addDay()->setTime(10, 0),
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('facilities:expire-pending')->expectsOutput('1 facility request(s) expired.')->assertSuccessful();

        $this->assertSame('expired', $booking->refresh()->status);
        $this->assertNotNull($booking->cancelled_at);
        Mail::assertQueued(FacilityBookingUpdate::class, fn (FacilityBookingUpdate $mail): bool => $mail->event === 'expired');
        $this->post(route('facilities.bookings.store', $facility), [
            ...$this->bookingData(),
            'starts_at' => '2026-11-15T09:00',
            'ends_at' => '2026-11-15T10:00',
        ])->assertSessionHasNoErrors();
    }

    public function test_unverified_requests_expire_and_stale_pending_requests_cannot_be_approved(): void
    {
        Mail::fake();
        $unverified = FacilityBooking::factory()->create([
            'status' => 'unverified',
            'reference' => 'FAC-UNVERIFIED01',
            'contact_email' => 'unverified@example.com',
            'expires_at' => now()->subMinute(),
        ]);
        $pending = FacilityBooking::factory()->create([
            'status' => 'pending',
            'reference' => 'FAC-STALEPENDING',
            'contact_email' => 'pending@example.com',
            'expires_at' => now()->subMinute(),
        ]);
        $this->verified(User::factory()->admin()->withTwoFactor()->create());

        $this->patch(route('admin.facilities.bookings.update', [$pending->facility, $pending]))->assertSessionHasErrors('booking');
        $this->assertSame('pending', $pending->refresh()->status);
        $this->artisan('facilities:expire-pending')->expectsOutput('2 facility request(s) expired.')->assertSuccessful();

        $this->assertSame('expired', $unverified->refresh()->status);
        $this->assertSame('Email verification deadline passed.', $unverified->cancellation_reason);
        $this->assertSame('expired', $pending->refresh()->status);
        $this->assertSame('Staff approval deadline passed.', $pending->cancellation_reason);
        Mail::assertQueued(FacilityBookingUpdate::class, 2);
    }

    public function test_recovery_response_does_not_disclose_unknown_requests(): void
    {
        Mail::fake();

        $this->post(route('facilities.bookings.recover'), ['reference' => 'FAC-UNKNOWN', 'contact_email' => 'unknown@example.com'])
            ->assertSessionHas('status', 'If those details match a request, a secure access link has been emailed.');

        Mail::assertNothingQueued();
    }

    public function test_facility_email_renders_status_and_escapes_requester_content(): void
    {
        $booking = FacilityBooking::factory()->create([
            'organizer' => '<script>alert(1)</script>',
            'reference' => 'FAC-SAFEEMAIL123',
            'contact_email' => 'owner@example.com',
        ]);

        $mail = new FacilityBookingUpdate($booking, 'approved');

        $mail->assertSeeInHtml('Facility request approved')
            ->assertSeeInHtml('&lt;script&gt;', false)
            ->assertDontSeeInHtml('<script>alert(1)</script>', false)
            ->assertSeeInText($booking->reference);
    }

    public function test_online_requests_cannot_bypass_policy_or_choose_demo_facilities(): void
    {
        $facility = Facility::factory()->create();
        $this->post(route('facilities.bookings.store', $facility), [])->assertSessionHasErrors(['organizer', 'contact_email', 'activity']);
        $demo = Facility::factory()->create(['source' => 'synthetic']);
        $this->post(route('facilities.bookings.store', $demo), [])->assertNotFound();
        $this->assertDatabaseCount('facility_bookings', 0);
    }

    /** @return array<string, mixed> */
    private function bookingData(): array
    {
        return [
            'title' => 'Council meeting',
            'organizer' => 'Council',
            'contact_email' => 'council@example.com',
            'activity' => 'Meeting',
            'attendees' => 10,
            'starts_at' => now()->addDays(2)->format('Y-m-d').'T09:00',
            'ends_at' => now()->addDays(2)->format('Y-m-d').'T10:00',
        ];
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession([
            'admin.mfa_user_id' => $user->id,
            'admin.mfa_fingerprint' => $user->adminSessionFingerprint(),
        ]);
    }
}
