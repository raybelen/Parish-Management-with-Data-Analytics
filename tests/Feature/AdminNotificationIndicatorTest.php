<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\OfficeNotification;
use App\Models\ParishSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminNotificationIndicatorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_indicator_counts_unacknowledged_appointment_and_facility_notices_in_the_current_workspace(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointmentNotice = OfficeNotification::factory()->create();
        $facilityNotice = OfficeNotification::factory()->create([
            'appointment_id' => null,
            'facility_booking_id' => FacilityBooking::factory(),
            'event' => 'facility.requested',
        ]);
        OfficeNotification::factory()->create(['read_at' => now()]);
        OfficeNotification::factory()->for(Appointment::factory()->create(['source' => 'synthetic']))->create();
        OfficeNotification::factory()->create([
            'appointment_id' => null,
            'facility_booking_id' => FacilityBooking::factory()->for(Facility::factory()->create(['source' => 'synthetic'])),
        ]);

        $this->get(route('admin.appointments.index'))->assertOk()
            ->assertSee('Office inbox: 2 awaiting attention')
            ->assertDontSee('Awaiting attention: 2');
        $this->patch(route('admin.notifications.update', $appointmentNotice))->assertRedirect();
        $this->get(route('admin.facilities.index'))->assertOk()
            ->assertSee('Office inbox: 1 awaiting attention');
        $this->patch(route('admin.notifications.update', $facilityNotice))->assertRedirect();
        $this->get(route('admin.notifications.index'))->assertOk()
            ->assertSee('Office inbox: 0 awaiting attention')
            ->assertDontSee('Awaiting attention: 0');
    }

    public function test_indicator_hides_notices_from_users_without_inbox_permission(): void
    {
        $this->withoutVite();
        ParishSetting::query()->updateOrCreate(['key' => 'office'], ['value' => ['permissions' => ['admin' => ['appointments.view']]]]);
        OfficeNotification::factory()->create();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());

        $this->get(route('admin.appointments.index'))->assertOk()->assertDontSee('Office inbox');
    }

    public function test_large_counts_are_capped_visually_but_keep_the_full_accessible_count(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        OfficeNotification::factory()->count(100)->for(Appointment::factory()->create())->create();

        $this->get(route('admin.appointments.index'))->assertOk()
            ->assertSee('Office inbox: 100 awaiting attention')->assertSee('99+');
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession([
            'admin.mfa_user_id' => $user->id,
            'admin.mfa_fingerprint' => $user->adminSessionFingerprint(),
        ]);
    }
}
