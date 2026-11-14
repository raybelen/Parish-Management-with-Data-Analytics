<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Personnel;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConfirmationBookingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_confirmation_keeps_one_hour_and_allows_back_to_back_bookings(): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        Personnel::factory()->create();

        $this->post(route('appointments.store'), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $this->postJson(route('appointments.availability'), [...$this->payload(), 'preferred_time' => '09:30'])
            ->assertJsonPath('available', false);
        $this->post(route('appointments.store'), [...$this->payload(), 'preferred_time' => '10:00'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('service_types', ['slug' => 'confirmation', 'duration_minutes' => 60, 'buffer_minutes' => 0]);
        $this->assertDatabaseCount('appointments', 2);
        foreach (Appointment::query()->get() as $appointment) {
            $this->assertTrue($appointment->ends_at->equalTo($appointment->starts_at->addHour()));
            $this->assertTrue($appointment->reserved_until->equalTo($appointment->ends_at));
            $this->assertSame(0, $appointment->buffer_minutes);
        }
    }

    public function test_confirmation_details_and_single_certificate_requirement_are_saved(): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        Personnel::factory()->create();
        $this->get(route('appointments.create', ['service' => 'confirmation']))
            ->assertSee('value="confirmation" data-fee-cents="10000" selected', false)
            ->assertSee('name="service[place_of_baptism]"', false)
            ->assertSee('name="service[godfather_name]"', false)
            ->assertSee('name="service[godmother_name]"', false);

        $this->post(route('appointments.store'), $this->payload())->assertRedirect()->assertSessionHasNoErrors();

        $appointment = Appointment::query()->sole();
        $this->assertSame('confirmation', $appointment->service_type);
        $this->assertSame(10000, $appointment->fee_cents);
        $this->assertSame($this->payload()['service'], $appointment->service_details);
        $this->assertSame(['baptismal_certificate' => ['label' => 'Baptismal Certificate', 'required' => true, 'multiple' => false]], $appointment->requirements);
        $this->assertFalse($appointment->physicalDocumentsComplete());
        $appointment->update(['physical_documents' => ['baptismal_certificate' => ['verified' => true]]]);
        $this->assertTrue($appointment->fresh()->physicalDocumentsComplete());
        $this->assertSame('Maria Santos', $appointment->participants()->where('role', 'recipient')->sole()->full_name);
    }

    public function test_confirmation_rejects_missing_details_and_invalid_age(): void
    {
        $this->travelTo('2026-10-01');
        $this->postJson(route('appointments.store'), [...$this->payload(), 'service' => ['age' => -1]])
            ->assertInvalid(['service.recipient_full_name', 'service.age', 'service.address', 'service.place_of_baptism', 'service.mother_name', 'service.father_name', 'service.godfather_name', 'service.godmother_name']);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_group_confirmation_shares_session_fee_and_priest_and_rejects_invalid_sessions(): void
    {
        $this->travelTo('2026-10-01');
        config(['parish.regular_schedules' => []]);
        Personnel::factory()->create();
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
        $this->postJson(route('admin.schedules.store'), [
            'title' => 'Group Confirmation', 'category' => 'Group Confirmation',
            'location' => 'St. John Nepomucene Parish', 'starts_at' => '2026-11-08T09:00',
            'ends_at' => '2026-11-08T10:00', 'status' => 'scheduled',
            'group_booking_open' => true, 'group_fee' => '250.00', 'recurrence' => 'none',
        ])->assertCreated();
        $session = Schedule::query()->sole();
        $this->assertTrue($session->priest_required);
        $this->get(route('appointments.create'))->assertSee('value="'.$session->id.'" data-service="confirmation"', false);
        $this->get(route('admin.appointments.create'))->assertSee('name="service[place_of_baptism]"', false);
        $payload = [...$this->payload(), 'baptism_mode' => 'shared', 'group_baptism_session_id' => $session->id];
        foreach (range(1, 2) as $candidate) {
            $this->post(route('appointments.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame(2, Appointment::query()->where('group_baptism_session_id', $session->id)->where('fee_cents', 25000)->where('personnel_id', $session->personnel_id)->count());
        $this->postJson(route('appointments.availability'), $payload)->assertJsonPath('available', true);
        $this->postJson(route('appointments.availability'), $this->payload())->assertJsonPath('available', false);
        $session->update(['category' => 'Group Baptism']);
        $this->postJson(route('appointments.store'), $payload)->assertInvalid('group_baptism_session_id');
        $session->update(['category' => 'Group Confirmation', 'group_booking_open' => false]);
        $this->postJson(route('appointments.store'), $payload)->assertInvalid('group_baptism_session_id');
        $this->assertDatabaseCount('appointments', 2);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'client_full_name' => 'Ana Santos', 'client_contact_number' => '09171234567',
            'client_address' => 'Cebu City', 'preferred_contact_method' => 'phone',
            'service_type' => 'confirmation', 'preferred_date' => '2026-11-08',
            'preferred_time' => '09:00', 'preferred_church' => 'St. John Nepomucene Parish',
            'payment_option' => 'walk_in',
            'service' => [
                'recipient_full_name' => 'Maria Santos', 'age' => 15, 'address' => 'Cebu City',
                'place_of_baptism' => 'St. John Nepomucene Parish', 'mother_name' => 'Ana Santos',
                'father_name' => 'Jose Santos', 'godfather_name' => 'Pedro Cruz', 'godmother_name' => 'Luz Cruz',
            ],
        ];
    }
}
