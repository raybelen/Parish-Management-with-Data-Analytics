<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\FacilityBooking;
use App\Models\OfficeNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminNotificationFilterTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{array<string, string>, list<int>}> */
    public static function filterCases(): array
    {
        return [
            'all' => [[], [2, 1, 0]],
            'attention' => [['status' => 'unread'], [2, 0]],
            'acknowledged' => [['status' => 'acknowledged'], [1]],
            'appointments' => [['type' => 'appointment'], [1, 0]],
            'facilities' => [['type' => 'facility'], [2]],
            'combined' => [['status' => 'unread', 'type' => 'appointment'], [0]],
            'empty' => [['status' => 'acknowledged', 'type' => 'facility'], []],
        ];
    }

    #[DataProvider('filterCases')]
    public function test_filters_show_only_matching_notices_without_changing_the_header_total(array $filters, array $expectedIndexes): void
    {
        $this->signIn();
        $notices = [
            OfficeNotification::factory()->create(),
            OfficeNotification::factory()->create(['read_at' => now()]),
            OfficeNotification::factory()->create(['appointment_id' => null, 'facility_booking_id' => FacilityBooking::factory()]),
        ];
        OfficeNotification::factory()->for(Appointment::factory()->create(['source' => 'synthetic']))->create();

        $response = $this->get(route('admin.notifications.index', $filters))->assertOk()
            ->assertSee('Office inbox: 2 awaiting attention');
        $this->assertSame(array_map(fn (int $index): int => $notices[$index]->id, $expectedIndexes), $response->viewData('notifications')->pluck('id')->all());
        $response->assertViewHas('filters', $filters);

        if ($expectedIndexes === []) {
            $response->assertSee('No notifications match these filters.');
        }
    }

    public function test_filters_are_preserved_in_pagination_and_after_acknowledgement(): void
    {
        $this->signIn();
        $notices = OfficeNotification::factory()->count(31)->for(Appointment::factory()->create())->create();
        $url = route('admin.notifications.index', ['status' => 'unread', 'type' => 'appointment']);
        $response = $this->get($url)->assertOk();
        parse_str(parse_url($response->viewData('notifications')->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame(['status' => 'unread', 'type' => 'appointment', 'page' => '2'], $query);

        $this->from($url)->patch(route('admin.notifications.update', $notices->first()))->assertRedirect($url);
        $this->assertNotNull($notices->first()->refresh()->read_at);
        $this->get($url)->assertViewHas('notifications', fn ($notifications): bool => $notifications->total() === 30);
    }

    public function test_invalid_filter_values_are_rejected(): void
    {
        $this->signIn();
        $this->from(route('admin.notifications.index'))->get(route('admin.notifications.index', ['status' => 'invalid', 'type' => ['facility']]))
            ->assertSessionHasErrors(['status', 'type']);
    }

    private function signIn(): void
    {
        $this->withoutVite();
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user)->withSession([
            'admin.mfa_user_id' => $user->id,
            'admin.mfa_fingerprint' => $user->adminSessionFingerprint(),
        ]);
    }
}
