<?php

namespace Tests\Feature;

use App\Mail\FacilityBookingUpdate;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\OfficeNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class OnlineFacilityBookingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_submitted_request_is_only_shown_in_the_flash_confirmation_and_not_saved_as_browser_history(): void
    {
        Mail::fake();
        $this->withoutVite();
        $this->travelTo('2026-11-14 08:00:00');
        $facility = Facility::factory()->create();

        $response = $this->post(route('facilities.bookings.store', $facility), $this->bookingData());

        $booking = FacilityBooking::query()->sole();
        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('facilities.index'))
            ->assertSessionMissing('facility_bookings')
            ->assertSessionHas('status', 'Your facility request is pending staff approval. The time is reserved only after approval.')
            ->assertSessionHas('facility_reference', $booking->reference);

        $this->get(route('facilities.index'))
            ->assertSee($booking->reference)
            ->assertSee('Facility reference')
            ->assertSee('Request received')
            ->assertDontSee('Your facility requests');

        $this->get(route('facilities.index'))
            ->assertSee('Check request status')
            ->assertDontSee($booking->reference)
            ->assertDontSee('Your facility requests');
    }

    public function test_competing_pending_requests_notify_staff_and_only_approval_reserves_time(): void
    {
        Mail::fake();
        $this->withoutVite();
        $this->travelTo('2026-11-14 08:00:00');
        $facility = Facility::factory()->create();
        $data = $this->bookingData();

        $this->post(route('facilities.bookings.store', $facility), $data)->assertSessionHasNoErrors()->assertRedirect(route('facilities.index'));

        $booking = FacilityBooking::query()->sole();
        $this->assertSame('pending', $booking->status);
        $this->assertSame('2026-11-15 09:00:00', $booking->expires_at->format('Y-m-d H:i:s'));
        Mail::assertQueued(FacilityBookingUpdate::class, fn (FacilityBookingUpdate $mail): bool => $mail->event === 'submitted' && $mail->details['reference'] === $booking->reference);

        $this->post(route('facilities.bookings.store', $facility), [...$data, 'contact_email' => 'second@example.com'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('facility_bookings', 2);
        $this->assertDatabaseHas('office_notifications', ['facility_booking_id' => $booking->id, 'event' => 'facility.requested']);
        $other = FacilityBooking::query()->where('id', '!=', $booking->id)->sole();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $this->get(route('admin.facilities.index'))->assertSee('Pending facility requests (2)')->assertSee($booking->reference)->assertSee($other->reference);
        $this->get(route('admin.notifications.index'))->assertSee('Review facility request')->assertSee($booking->reference);
        $notice = OfficeNotification::query()->where('facility_booking_id', $booking->id)->sole();
        $this->patch(route('admin.notifications.update', $notice))->assertRedirect();
        $this->assertNotNull($notice->refresh()->read_at);
        $hidden = FacilityBooking::factory()->create(['facility_id' => Facility::factory()->create(['source' => 'synthetic']), 'status' => 'pending', 'reference' => 'HIDDEN-DEMO-REQUEST', 'expires_at' => now()->addDay()]);
        $hiddenNotice = OfficeNotification::create(['facility_booking_id' => $hidden->id, 'event' => 'facility.requested', 'message' => 'HIDDEN-DEMO-NOTICE', 'deduplication_key' => 'hidden-facility']);
        $this->get(route('admin.facilities.index'))->assertDontSee('HIDDEN-DEMO-REQUEST');
        $this->get(route('admin.notifications.index'))->assertDontSee('HIDDEN-DEMO-NOTICE');
        $this->patch(route('admin.notifications.update', $hiddenNotice))->assertNotFound();
        $this->patch(route('admin.facilities.bookings.update', [$facility, $booking]))->assertSessionHasNoErrors();
        $this->patch(route('admin.facilities.bookings.update', [$facility, $other]))->assertSessionHasErrors('booking');
        $this->assertSame('confirmed', $booking->refresh()->status);
        $this->assertSame('pending', $other->refresh()->status);
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

    public function test_reference_and_email_lookup_issues_session_bound_single_use_status_access(): void
    {
        $this->withoutVite();
        $booking = FacilityBooking::factory()->create([
            'reference' => 'FAC-ABCDEFGHIJKLMNOP',
            'contact_email' => 'owner@example.com',
        ]);

        $lookup = $this->post(route('facilities.lookup.store'), ['reference' => strtolower($booking->reference), 'contact_email' => 'OWNER@example.com']);
        $location = $lookup->headers->get('Location');
        $this->assertNotNull($location);
        $this->get(route('facilities.bookings.show', $booking->reference))->assertForbidden();

        $firstVisit = $this->get($location);
        $firstVisit->assertSee($booking->reference)->assertSee('Approved')->assertSee('data-one-time-client-access', false)->assertHeader('Pragma', 'no-cache');
        $this->assertStringContainsString('no-store', (string) $firstVisit->headers->get('Cache-Control'));
        $this->get($location)->assertRedirect(route('facilities.lookup.create'))->assertSessionHas('facility_access_notice');
        $this->withSession(['facility_booking_access_tokens' => []])->get($location)->assertRedirect(route('facilities.lookup.create'));

        $this->delete(route('facilities.bookings.destroy', $booking->reference), ['reason' => 'Unsigned'])->assertForbidden();
        $cancelUrl = URL::temporarySignedRoute('facilities.bookings.destroy', now()->addHour(), ['booking' => $booking->reference]);
        $this->delete($cancelUrl, ['reason' => 'Plans changed'])->assertRedirect(route('facilities.lookup.create'));
        $this->assertSame('cancelled', $booking->refresh()->status);
        $this->assertSame('Plans changed', $booking->cancellation_reason);
    }

    public function test_recovery_displays_matching_reference_numbers_without_sending_email(): void
    {
        Mail::fake();
        $facility = Facility::factory()->create(['name' => 'Function Hall']);
        $booking = FacilityBooking::factory()->create([
            'facility_id' => $facility,
            'organizer' => 'Parish Council',
            'reference' => 'FAC-RECOVERYLINK123',
            'contact_email' => 'owner@example.com',
        ]);

        $response = $this->post(route('facilities.bookings.recover'), ['organizer' => '  parish   council ', 'contact_email' => 'OWNER@example.com']);

        $response->assertViewIs('client-side.facility-manage')
            ->assertSee('Your facility references')
            ->assertSee($booking->reference)
            ->assertSee('Function Hall')
            ->assertSee('data-copy-reference="'.$booking->reference.'"', false)
            ->assertSee('View request details')
            ->assertHeader('Pragma', 'no-cache');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        Mail::assertNothingQueued();
    }

    public function test_pending_request_expires_at_the_approval_cutoff_and_notifies_the_requester(): void
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
            'expires_at' => now()->addHour(),
        ]);

        $this->artisan('facilities:expire-pending')->expectsOutput('0 facility request(s) expired.')->assertSuccessful();
        $this->assertSame('pending', $booking->refresh()->status);

        $this->travelTo('2026-11-14 09:00:00');
        $this->artisan('facilities:expire-pending')->expectsOutput('1 facility request(s) expired.')->assertSuccessful();

        $this->assertSame('expired', $booking->refresh()->status);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame('Staff approval deadline passed 24 hours before the requested schedule.', $booking->cancellation_reason);
        Mail::assertQueued(FacilityBookingUpdate::class, fn (FacilityBookingUpdate $mail): bool => $mail->event === 'expired');
    }

    public function test_online_request_requires_more_than_twenty_four_hours_notice(): void
    {
        $this->travelTo('2026-11-14 08:00:00');
        $facility = Facility::factory()->create();

        $this->post(route('facilities.bookings.store', $facility), [
            ...$this->bookingData(),
            'starts_at' => '2026-11-15T08:00',
            'ends_at' => '2026-11-15T09:00',
        ])->assertSessionHasErrors([
            'starts_at' => 'Online facility requests must be submitted more than 24 hours before the requested start time.',
        ]);

        $this->assertDatabaseCount('facility_bookings', 0);
    }

    public function test_same_organizer_and_email_are_limited_to_three_successful_requests_per_day(): void
    {
        Mail::fake();
        $this->travelTo('2026-11-14 08:00:00');
        $facilities = Facility::factory()->count(4)->create();

        foreach ($facilities->take(3) as $index => $facility) {
            $this->post(route('facilities.bookings.store', $facility), [
                ...$this->bookingData(),
                'organizer' => $index % 2 === 0 ? '  Parish   Council  ' : 'PARISH COUNCIL',
                'contact_email' => $index % 2 === 0 ? 'COUNCIL@example.com' : 'council@example.com',
            ])->assertSessionHasNoErrors();

            if ($index === 0) {
                FacilityBooking::factory()->create(['facility_id' => $facility->id, 'starts_at' => '2026-11-16 11:00:00', 'ends_at' => '2026-11-16 12:00:00']);
                $this->post(route('facilities.bookings.store', $facility), [
                    ...$this->bookingData(),
                    'starts_at' => '2026-11-16T11:00',
                    'ends_at' => '2026-11-16T12:00',
                    'organizer' => 'parish council',
                    'contact_email' => 'council@example.com',
                ])->assertSessionHasErrors('starts_at');
            }
        }

        $this->post(route('facilities.bookings.store', $facilities->last()), [
            ...$this->bookingData(),
            'organizer' => 'parish council',
            'contact_email' => 'council@example.com',
        ])->assertSessionHasErrors([
            'organizer' => 'This organizer and email have reached the limit of 3 facility requests within 24 hours. Please try again later or contact the parish office.',
        ]);

        $this->assertDatabaseCount('facility_bookings', 4);
        Mail::assertQueued(FacilityBookingUpdate::class, 3);
    }

    public function test_sixth_submission_attempt_from_same_ip_within_one_minute_is_rate_limited(): void
    {
        $facility = Facility::factory()->create();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('facilities.bookings.store', $facility), [])->assertSessionHasErrors('organizer');
        }

        $this->post(route('facilities.bookings.store', $facility), [])->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertDatabaseCount('facility_bookings', 0);
    }

    public function test_stale_pending_requests_cannot_be_approved_and_are_expired(): void
    {
        Mail::fake();
        $pending = FacilityBooking::factory()->create([
            'status' => 'pending',
            'reference' => 'FAC-STALEPENDING',
            'contact_email' => 'pending@example.com',
            'expires_at' => now()->subMinute(),
        ]);
        $this->verified(User::factory()->admin()->withTwoFactor()->create());

        $this->patch(route('admin.facilities.bookings.update', [$pending->facility, $pending]))->assertSessionHasErrors('booking');
        $this->assertSame('pending', $pending->refresh()->status);
        $this->artisan('facilities:expire-pending')->expectsOutput('1 facility request(s) expired.')->assertSuccessful();

        $this->assertSame('expired', $pending->refresh()->status);
        $this->assertSame('Staff approval deadline passed 24 hours before the requested schedule.', $pending->cancellation_reason);
        Mail::assertQueued(FacilityBookingUpdate::class, 1);
    }

    public function test_recovery_reports_when_no_request_matches(): void
    {
        Mail::fake();

        $this->post(route('facilities.bookings.recover'), ['organizer' => 'Unknown Group', 'contact_email' => 'unknown@example.com'])
            ->assertSessionHasErrors([
                'organizer' => 'We could not find a facility request matching those details.',
            ], null, 'recovery');

        Mail::assertNothingQueued();
    }

    public function test_status_checking_and_reference_recovery_are_not_rate_limited(): void
    {
        $booking = FacilityBooking::factory()->create([
            'organizer' => 'Parish Council',
            'reference' => 'FAC-UNLIMITEDCHECK1',
            'contact_email' => 'owner@example.com',
        ]);

        for ($attempt = 1; $attempt <= 11; $attempt++) {
            $this->post(route('facilities.lookup.store'), [
                'reference' => $booking->reference,
                'contact_email' => $booking->contact_email,
            ])->assertRedirect();
        }

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post(route('facilities.bookings.recover'), [
                'organizer' => $booking->organizer,
                'contact_email' => $booking->contact_email,
            ])->assertOk();
        }
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

    public function test_submission_email_confirms_pending_status_and_provides_status_access(): void
    {
        $booking = FacilityBooking::factory()->create([
            'status' => 'pending',
            'reference' => 'FAC-SUBMITTED1234',
            'contact_email' => 'owner@example.com',
        ]);

        $mail = new FacilityBookingUpdate($booking, 'submitted');

        $mail->assertSeeInHtml('Facility request submitted')
            ->assertSeeInHtml('pending parish staff approval')
            ->assertSeeInHtml('View request')
            ->assertSeeInText($booking->reference);
        $this->assertStringContainsString('/email-access?', $mail->accessUrl);
    }

    public function test_online_requests_cannot_bypass_policy_or_choose_demo_facilities(): void
    {
        $facility = Facility::factory()->create();
        $this->post(route('facilities.bookings.store', $facility), [])->assertSessionHasErrors(['organizer', 'contact_email', 'activity']);
        $demo = Facility::factory()->create(['source' => 'synthetic']);
        $this->post(route('facilities.bookings.store', $demo), [])->assertNotFound();
        $this->assertDatabaseCount('facility_bookings', 0);
    }

    public function test_attendee_count_over_facility_capacity_receives_clear_feedback(): void
    {
        $facility = Facility::factory()->create(['capacity' => 50]);

        $this->post(route('facilities.bookings.store', $facility), [
            ...$this->bookingData(),
            'attendees' => 60,
        ])->assertSessionHasErrors([
            'attendees' => 'This facility can accommodate up to 50 people. Reduce the attendee count before submitting.',
        ]);

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
