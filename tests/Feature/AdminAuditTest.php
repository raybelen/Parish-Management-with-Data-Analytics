<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminAuditTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_audit_trail_presents_readable_activity_and_hides_technical_fields(): void
    {
        $this->withoutVite();
        $viewer = User::factory()->superAdmin()->withTwoFactor()->create();
        $actor = User::factory()->admin()->create(['name' => 'Maria Santos']);
        $appointment = Appointment::factory()->create();
        AuditLog::factory()->create([
            'actor_id' => $actor->id,
            'action' => 'appointment.fee_changed',
            'entity_type' => Appointment::class,
            'entity_id' => $appointment->id,
            'before' => ['fee_cents' => 100000, 'version' => 2, 'personnel_id' => 8],
            'after' => ['fee_cents' => 150000, 'version' => 3, 'personnel_id' => 9],
            'reason' => 'Approved parish rate adjustment',
        ]);

        $response = $this->verified($viewer)->get(route('admin.audit.index'));

        $response->assertSee('Appointment fee changed')
            ->assertSee('Payments &amp; billing', false)
            ->assertSee('Maria Santos')
            ->assertSee('Approved parish rate adjustment')
            ->assertSee('Fee')
            ->assertSee('₱1,000.00')
            ->assertSee('₱1,500.00')
            ->assertDontSee('fee_cents')
            ->assertDontSee('personnel_id')
            ->assertDontSee('App\\Models\\Appointment')
            ->assertDontSee('User ID')
            ->assertDontSee('<pre', false);
    }

    public function test_audit_trail_can_be_filtered_by_friendly_category(): void
    {
        $this->withoutVite();
        $viewer = User::factory()->superAdmin()->withTwoFactor()->create();
        AuditLog::factory()->create(['action' => 'payment.payment', 'reason' => 'Visible payment note']);
        AuditLog::factory()->create(['action' => 'donation.recorded', 'reason' => 'Hidden donation note']);

        $this->verified($viewer)
            ->get(route('admin.audit.index', ['category' => 'payments']))
            ->assertSee('Payment recorded')
            ->assertSee('Visible payment note')
            ->assertDontSee('Hidden donation note');
    }

    public function test_audit_trail_search_accepts_the_readable_activity_name(): void
    {
        $this->withoutVite();
        $viewer = User::factory()->superAdmin()->withTwoFactor()->create();
        AuditLog::factory()->create(['action' => 'payment.payment', 'reason' => 'Matching entry']);
        AuditLog::factory()->create(['action' => 'donation.recorded', 'reason' => 'Unrelated entry']);

        $this->verified($viewer)
            ->get(route('admin.audit.index', ['q' => 'payment recorded']))
            ->assertSee('Matching entry')
            ->assertDontSee('Unrelated entry');
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession([
            'admin.mfa_user_id' => $user->id,
            'admin.mfa_fingerprint' => $user->adminSessionFingerprint(),
        ]);
    }
}
