<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\ParishSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminFacilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_facility_pages_require_login_mfa_and_permission(): void
    {
        $this->get('/admin/facilities')->assertRedirect('/login');
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user)->get('/admin/facilities')->assertRedirect();
        $setting = ParishSetting::query()->where('key', 'office')->firstOrFail();
        $setting->update(['value' => [...$setting->value, 'permissions' => ['admin' => []]]]);
        $this->verified($user);

        $this->get('/admin/facilities')->assertForbidden();
        $this->post('/admin/facilities', $this->facilityData())->assertForbidden();
        $this->assertDatabaseCount('facilities', 0);
    }

    public function test_staff_can_create_edit_and_find_a_facility_with_an_audit_trail(): void
    {
        $this->verified();
        $this->get('/admin/facilities/create')->assertSee('Weekly opening hours');
        $this->post('/admin/facilities', $this->facilityData())->assertSessionHasNoErrors()->assertRedirect();
        $facility = Facility::query()->sole();
        $this->assertSame(['Catechism class', 'Parish gathering'], $facility->permitted_activities);
        $this->assertDatabaseHas('audit_logs', ['action' => 'facility.created', 'entity_id' => $facility->id]);
        $this->get('/admin/facilities?search=Hall&status=active')->assertSee('Parish Hall');
        $this->get('/admin/facilities/'.$facility->id.'/edit')->assertSee('Edit facility');

        $this->put('/admin/facilities/'.$facility->id, [...$this->facilityData(), 'capacity' => 80])->assertSessionHasNoErrors();
        $facility->refresh();
        $this->assertSame(80, $facility->capacity);
        $this->assertDatabaseHas('audit_logs', ['action' => 'facility.updated', 'entity_id' => $facility->id]);
    }

    public function test_invalid_facility_details_are_rejected_without_saving(): void
    {
        $this->verified();
        $data = $this->facilityData();
        $data['capacity'] = 0;
        $data['permitted_activities'] = "Catechism class\ncatechism class";
        $data['opening_hours'][1]['closes_at'] = '07:00';
        $this->post('/admin/facilities', $data)->assertSessionHasErrors(['capacity', 'permitted_activities.0', 'opening_hours.1.closes_at']);
        $this->assertDatabaseCount('facilities', 0);
    }

    public function test_closed_days_discard_time_fields_and_duplicate_names_are_rejected(): void
    {
        $this->verified();
        $data = $this->facilityData();
        $data['opening_hours'][7] = ['open' => '0', 'opens_at' => '', 'closes_at' => ''];
        $this->post('/admin/facilities', $data)->assertSessionHasNoErrors();
        $facility = Facility::query()->sole();
        $this->assertFalse((bool) $facility->opening_hours[7]['open']);
        $this->post('/admin/facilities', $data)->assertSessionHasErrors('name');
        $this->assertDatabaseCount('facilities', 1);
    }

    public function test_booking_records_schedule_and_ignores_unvalidated_fields(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(8, 0));
        $this->verified();
        $facility = Facility::factory()->create();
        $this->post(route('admin.facilities.bookings.store', $facility), [...$this->bookingData(), 'ends_at' => '2026-11-16T10:30', 'status' => 'pending'])->assertSessionHasNoErrors();

        $booking = FacilityBooking::query()->sole();
        $this->assertSame(auth()->id(), $booking->recorded_by);
        $this->assertSame('confirmed', $booking->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'facility.reservation.created', 'entity_id' => $booking->id]);
        $this->get(route('admin.facilities.show', ['facility' => $facility, 'date' => '2026-11-16']))->assertSee('Council meeting')->assertSee('8:00 AM - 9:00 AM')->assertSee('10:30 AM - 6:00 PM');
    }

    #[DataProvider('invalidReservations')]
    public function test_reservations_obey_facility_policies(array $changes, array $facilityChanges, string $message): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(8, 0));
        $this->verified();
        $facility = Facility::factory()->create($facilityChanges);
        $this->post(route('admin.facilities.bookings.store', $facility), [...$this->bookingData(), ...$changes])->assertSessionHasErrors(['starts_at' => $message]);
        $this->assertDatabaseCount('facility_bookings', 0);
    }

    public static function invalidReservations(): array
    {
        return [
            'inactive' => [[], ['active' => false], 'This facility is inactive.'],
            'over capacity' => [['attendees' => 101], [], 'The attendee count exceeds this facility\'s capacity.'],
            'activity' => [['activity' => 'Concert'], [], 'Choose an activity permitted at this facility.'],
            'overnight' => [['ends_at' => '2026-11-17T10:00'], [], 'Reservations must start and finish on the same day.'],
            'closed weekday' => [[], ['opening_hours' => array_fill(1, 7, ['open' => false])], 'This facility is closed on the selected day.'],
            'before opening' => [['starts_at' => '2026-11-16T07:59'], [], 'The reservation must fit within the facility\'s opening hours.'],
            'after closing' => [['ends_at' => '2026-11-16T18:01'], [], 'The reservation must fit within the facility\'s opening hours.'],
        ];
    }

    public function test_conflicts_reject_double_booking_and_closures_but_allow_adjacent_times(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(8, 0));
        $this->verified();
        $facility = Facility::factory()->create();
        $this->post(route('admin.facilities.bookings.store', $facility), $this->bookingData())->assertSessionHasNoErrors();
        $this->post(route('admin.facilities.bookings.store', $facility), $this->bookingData())->assertSessionHasErrors('starts_at');
        $this->post(route('admin.facilities.bookings.store', $facility), [...$this->bookingData(), 'kind' => 'closure'])->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('facility_bookings', 1);
        $this->post(route('admin.facilities.bookings.store', $facility), [...$this->bookingData(), 'starts_at' => '2026-11-16T10:00', 'ends_at' => '2026-11-16T11:00'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('facility_bookings', 2);
    }

    public function test_multi_day_closures_block_bookings_and_availability_until_canceled(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(8, 0));
        $this->verified();
        $facility = Facility::factory()->create();
        $this->post(route('admin.facilities.bookings.store', $facility), ['kind' => 'closure', 'title' => 'Floor repairs', 'starts_at' => '2026-11-15T08:00', 'ends_at' => '2026-11-17T18:00'])->assertSessionHasNoErrors();
        $closure = FacilityBooking::query()->sole();
        $this->assertNull($closure->organizer);
        $this->get(route('admin.facilities.show', ['facility' => $facility, 'date' => '2026-11-16']))->assertSee('No available periods on this day.');
        $this->post(route('admin.facilities.bookings.store', $facility), $this->bookingData())->assertSessionHasErrors('starts_at');
        $this->delete(route('admin.facilities.bookings.destroy', [$facility, $closure]), ['reason' => 'Repairs finished early'])->assertSessionHasNoErrors();
        $this->assertNotNull($closure->refresh()->cancelled_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'facility.closure.cancelled', 'entity_id' => $closure->id, 'reason' => 'Repairs finished early']);
        $this->post(route('admin.facilities.bookings.store', $facility), $this->bookingData())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('facility_bookings', 2);
    }

    public function test_policy_changes_cannot_invalidate_existing_reservations(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(8, 0));
        $this->verified();
        $facility = Facility::factory()->create();
        $this->post(route('admin.facilities.bookings.store', $facility), $this->bookingData())->assertSessionHasNoErrors();
        $data = [...$this->facilityData(), 'name' => $facility->name, 'active' => '0'];
        $this->put(route('admin.facilities.update', $facility), $data)->assertSessionHasErrors('facility');
        $this->assertTrue($facility->refresh()->active);
        $this->put(route('admin.facilities.update', $facility), [...$data, 'active' => '1', 'capacity' => 1])->assertSessionHasErrors('facility');
        $this->put(route('admin.facilities.update', $facility), [...$data, 'active' => '1'])->assertSessionHasNoErrors();
    }

    public function test_cancellation_is_audited_once_and_keeps_history(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(8, 0));
        $this->verified();
        $booking = FacilityBooking::factory()->create();
        $route = route('admin.facilities.bookings.destroy', [$booking->facility, $booking]);
        $this->delete($route, [])->assertSessionHasErrors('reason');
        $this->assertNull($booking->refresh()->cancelled_at);
        $this->delete($route, ['reason' => 'Organizer canceled'])->assertSessionHasNoErrors();
        $this->delete($route, ['reason' => 'Duplicate request'])->assertSessionHasNoErrors();
        $this->assertSame('Organizer canceled', $booking->refresh()->cancellation_reason);
        $this->assertDatabaseCount('facility_bookings', 1);
        $this->assertSame(1, AuditLog::query()->where('action', 'facility.reservation.cancelled')->count());
    }

    public function test_other_sources_and_wrong_facility_bookings_cannot_be_accessed(): void
    {
        $this->verified();
        $hidden = Facility::factory()->create(['source' => 'synthetic', 'name' => 'Hidden demo hall']);
        $facility = Facility::factory()->create();
        $otherBooking = FacilityBooking::factory()->create();
        $this->get('/admin/facilities')->assertDontSee('Hidden demo hall');
        $this->get(route('admin.facilities.show', $hidden))->assertNotFound();
        $this->get(route('admin.facilities.edit', $hidden))->assertNotFound();
        $this->put(route('admin.facilities.update', $hidden), $this->facilityData())->assertNotFound();
        $this->post(route('admin.facilities.bookings.store', $hidden), $this->bookingData())->assertNotFound();
        $this->delete(route('admin.facilities.bookings.destroy', [$facility, $otherBooking]), ['reason' => 'Wrong facility'])->assertNotFound();
        $this->assertNull($otherBooking->refresh()->cancelled_at);
    }

    public function test_read_only_staff_can_view_but_cannot_mutate_facilities_or_bookings(): void
    {
        $this->verified();
        $setting = ParishSetting::query()->where('key', 'office')->firstOrFail();
        $setting->update(['value' => [...$setting->value, 'permissions' => ['admin' => ['facilities.view']]]]);
        $booking = FacilityBooking::factory()->create();
        $facility = $booking->facility;
        $this->get(route('admin.facilities.show', $facility))->assertSee($facility->name)->assertDontSee('Save reservation');
        $this->get('/admin/facilities/create')->assertForbidden();
        $this->get(route('admin.facilities.edit', $facility))->assertForbidden();
        $this->put(route('admin.facilities.update', $facility), $this->facilityData())->assertForbidden();
        $this->post(route('admin.facilities.bookings.store', $facility), $this->bookingData())->assertForbidden();
        $this->delete(route('admin.facilities.bookings.destroy', [$facility, $booking]), ['reason' => 'No permission'])->assertForbidden();
        $this->assertNull($booking->refresh()->cancelled_at);
    }

    public function test_pages_escape_user_text_and_paginate_facilities(): void
    {
        $this->verified();
        $facility = Facility::factory()->create(['name' => '<script>alert(1)</script>']);
        Facility::factory()->count(12)->create();
        $this->get('/admin/facilities')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('page=2', false);
        $this->get(route('admin.facilities.show', $facility))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_invalid_or_past_booking_times_do_not_create_records(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(8, 0));
        $this->verified();
        $facility = Facility::factory()->create();
        $url = route('admin.facilities.bookings.store', $facility);
        $this->post($url, [])->assertSessionHasErrors(['kind', 'title', 'starts_at', 'ends_at']);
        $this->post($url, [...$this->bookingData(), 'starts_at' => '2026-11-12T09:00'])->assertSessionHasErrors('starts_at');
        $this->post($url, [...$this->bookingData(), 'ends_at' => '2026-11-16T09:00'])->assertSessionHasErrors('ends_at');
        $this->post($url, [...$this->bookingData(), 'organizer' => '', 'attendees' => 0])->assertSessionHasErrors(['organizer', 'attendees']);
        $this->assertDatabaseCount('facility_bookings', 0);
    }

    public function test_past_bookings_cannot_be_canceled_and_do_not_prevent_archiving_a_facility(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(8, 0));
        $this->verified();
        $booking = FacilityBooking::factory()->create(['starts_at' => now()->subDay()->setTime(9, 0), 'ends_at' => now()->subDay()->setTime(10, 0)]);
        $facility = $booking->facility;
        $this->delete(route('admin.facilities.bookings.destroy', [$facility, $booking]), ['reason' => 'Erase history'])->assertSessionHasErrors('reason');
        $this->assertNull($booking->refresh()->cancelled_at);
        $this->put(route('admin.facilities.update', $facility), [...$this->facilityData(), 'active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($facility->refresh()->active);
        $this->assertModelExists($booking);
    }

    public function test_bookings_in_other_facilities_do_not_block_the_selected_room(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(8, 0));
        $this->verified();
        $facility = Facility::factory()->create();
        FacilityBooking::factory()->create(['starts_at' => '2026-11-16 09:00', 'ends_at' => '2026-11-16 10:00']);
        $this->post(route('admin.facilities.bookings.store', $facility), $this->bookingData())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('facility_bookings', 2);
        $this->get(route('admin.facilities.show', $facility))->assertSee('Upcoming reservations &amp; closures', false)->assertSee('Council meeting');
    }

    public function test_facility_input_cannot_change_its_dataset_or_store_unvalidated_hours(): void
    {
        $this->verified();
        $data = [...$this->facilityData(), 'source' => 'synthetic'];
        $this->post('/admin/facilities', $data)->assertSessionHasNoErrors();
        $facility = Facility::query()->sole();
        $this->assertSame('operational', $facility->source);
        $data['opening_hours'][1]['unexpected'] = 'injected';
        $this->put(route('admin.facilities.update', $facility), $data)->assertSessionHasErrors('opening_hours.1');
        $this->assertArrayNotHasKey('unexpected', $facility->refresh()->opening_hours[1]);
    }

    /** @return array<string, mixed> */
    private function facilityData(): array
    {
        return [
            'name' => 'Parish Hall', 'location' => 'Parish compound', 'description' => 'Multi-purpose hall',
            'capacity' => 100, 'active' => '1', 'permitted_activities' => "Catechism class\nParish gathering",
            'opening_hours' => array_fill(1, 7, ['open' => '1', 'opens_at' => '08:00', 'closes_at' => '18:00']),
        ];
    }

    /** @return array<string, mixed> */
    private function bookingData(): array
    {
        return ['kind' => 'reservation', 'title' => 'Council meeting', 'organizer' => 'Parish council', 'activity' => 'Catechism class', 'attendees' => 25, 'starts_at' => '2026-11-16T09:00', 'ends_at' => '2026-11-16T10:00'];
    }

    private function verified(?User $user = null): static
    {
        $user ??= User::factory()->admin()->withTwoFactor()->create();
        $this->withoutVite();

        return $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }
}
