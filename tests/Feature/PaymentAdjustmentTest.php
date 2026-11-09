<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentAdjustmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_refund_request_leaves_balance_unchanged_until_a_different_super_admin_approves(): void
    {
        $this->travelTo('2026-11-08 12:00');
        $maker = User::factory()->admin()->withTwoFactor()->create();
        $appointment = Appointment::factory()->create(['fee_cents' => 10000, 'payment_status' => 'paid']);
        $payment = Payment::factory()->for($appointment)->create(['paid_at' => '2026-11-07']);
        $payload = $this->refund($payment);
        $this->verified($maker)->post(route('admin.payments.store', $appointment), [...$payload, 'status' => 'approved', 'reviewed_by' => $maker->id])->assertSessionHasNoErrors();
        $this->post(route('admin.payments.store', $appointment), $payload)->assertSessionHasNoErrors();
        $adjustment = PaymentAdjustment::query()->sole();
        $this->assertSame('pending', $adjustment->status);
        $this->assertNull($adjustment->reviewed_by);
        $this->assertSame(0, $appointment->refresh()->balanceCents());
        $this->assertDatabaseCount('payments', 1);
        $this->patch(route('admin.payment-adjustments.update', $adjustment), ['decision' => 'approved', 'reason' => 'Bypass'])->assertForbidden();

        $reviewer = User::factory()->superAdmin()->withTwoFactor()->create();
        $this->verified($reviewer)->patch(route('admin.payment-adjustments.update', $adjustment), ['decision' => 'approved', 'reason' => 'Receipt checked'])->assertSessionHasNoErrors();

        $adjustment->refresh();
        $this->assertSame('approved', $adjustment->status);
        $this->assertSame($reviewer->id, $adjustment->reviewed_by);
        $this->assertSame($maker->id, $adjustment->requested_by);
        $this->assertNotNull($adjustment->reviewed_at);
        $this->assertSame(2500, $appointment->refresh()->balanceCents());
        $this->assertSame('partial', $appointment->payment_status);
        $this->assertDatabaseHas('payments', ['id' => $adjustment->payment_id, 'kind' => 'refund', 'amount_cents' => 2500, 'recorded_by' => $reviewer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment_adjustment.requested', 'actor_id' => $maker->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment_adjustment.approved', 'actor_id' => $reviewer->id]);
        $this->patch(route('admin.payment-adjustments.update', $adjustment), ['decision' => 'approved', 'reason' => 'Retry'])->assertConflict();
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_fee_request_is_idempotent_and_approval_applies_only_the_saved_amount(): void
    {
        $appointment = Appointment::factory()->create(['fee_cents' => 10000]);
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $payload = ['idempotency_key' => (string) Str::uuid(), 'fee' => '50.00', 'version' => 1, 'reason' => 'Reduced fee'];
        $url = route('admin.payments.fee', $appointment);
        $this->patch($url, $payload)->assertSessionHasNoErrors();
        $this->patch($url, $payload)->assertSessionHasNoErrors();
        $this->patch($url, [...$payload, 'fee' => '0'])->assertConflict();
        $adjustment = PaymentAdjustment::query()->sole();
        $this->assertSame(10000, $appointment->refresh()->fee_cents);
        $this->assertSame(1, $appointment->version);

        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create())->patch(route('admin.payment-adjustments.update', $adjustment),
            ['decision' => 'approved', 'reason' => 'Approved reduction', 'fee' => '0', 'payload' => ['fee' => '0']])->assertSessionHasNoErrors();

        $this->assertSame(5000, $appointment->refresh()->fee_cents);
        $this->assertSame(2, $appointment->version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.fee_changed', 'entity_id' => $appointment->id]);
    }

    public function test_super_admin_cannot_review_own_request_even_after_a_role_change(): void
    {
        $maker = User::factory()->admin()->withTwoFactor()->create();
        $appointment = Appointment::factory()->create(['fee_cents' => 10000]);
        $adjustment = PaymentAdjustment::factory()->for($appointment)->create(['requested_by' => $maker->id]);
        $maker->role = User::ROLE_SUPER_ADMIN;
        $maker->save();
        $this->withoutVite();
        $this->verified($maker);

        $this->get(route('admin.payment-adjustments.index'))->assertSee('A different super administrator must review your request.')->assertDontSee('Approve request');
        $this->patch(route('admin.payment-adjustments.update', $adjustment), ['decision' => 'approved', 'reason' => 'Self approval'])->assertForbidden();
        $this->assertSame('pending', $adjustment->refresh()->status);
        $this->assertSame(10000, $appointment->refresh()->fee_cents);
    }

    public function test_rejection_preserves_balances_and_cannot_be_reopened(): void
    {
        $appointment = Appointment::factory()->create(['fee_cents' => 10000]);
        $adjustment = PaymentAdjustment::factory()->for($appointment)->create();
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());
        $url = route('admin.payment-adjustments.update', $adjustment);

        $this->patch($url, ['decision' => 'rejected', 'reason' => 'Insufficient supporting documents'])->assertSessionHasNoErrors();
        $this->patch($url, ['decision' => 'approved', 'reason' => 'Changed mind'])->assertConflict();
        $this->assertSame('rejected', $adjustment->refresh()->status);
        $this->assertSame(10000, $appointment->refresh()->fee_cents);
        $this->assertDatabaseEmpty('payments');
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment_adjustment.rejected']);
    }

    public function test_super_admin_requests_also_wait_for_another_reviewer(): void
    {
        $this->travelTo('2026-11-08 12:00');
        $payment = Payment::factory()->create(['paid_at' => '2026-11-07']);
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());

        $this->post(route('admin.payments.store', $payment->appointment), $this->refund($payment))->assertSessionHasNoErrors();

        $adjustment = PaymentAdjustment::query()->sole();
        $this->assertSame('pending', $adjustment->status);
        $this->assertDatabaseCount('payments', 1);
        $this->patch(route('admin.payment-adjustments.update', $adjustment), ['decision' => 'approved', 'reason' => 'Self review'])->assertForbidden();
    }

    public function test_submitted_request_details_cannot_be_edited(): void
    {
        $adjustment = PaymentAdjustment::factory()->create();
        $this->expectException(\LogicException::class);

        $adjustment->update(['payload' => ['fee' => '0']]);
    }

    public function test_completed_review_cannot_be_edited(): void
    {
        $adjustment = PaymentAdjustment::factory()->create(['status' => 'rejected']);
        $adjustment->status = 'approved';
        $this->expectException(\LogicException::class);

        $adjustment->save();
    }

    public function test_refund_request_key_cannot_be_reused_for_a_routine_payment(): void
    {
        $this->travelTo('2026-11-08 12:00');
        $payment = Payment::factory()->create(['paid_at' => '2026-11-07']);
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $payload = $this->refund($payment);
        $this->post(route('admin.payments.store', $payment->appointment), $payload)->assertSessionHasNoErrors();

        $this->post(route('admin.payments.store', $payment->appointment), [...$payload, 'kind' => 'payment'])->assertConflict();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_adjustments', 1);
    }

    public function test_stale_fee_request_cannot_overwrite_a_newer_appointment(): void
    {
        $appointment = Appointment::factory()->create(['fee_cents' => 10000]);
        $adjustment = PaymentAdjustment::factory()->for($appointment)->create();
        $appointment->update(['version' => 2]);
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());

        $this->patch(route('admin.payment-adjustments.update', $adjustment), ['decision' => 'approved', 'reason' => 'Reviewed'])->assertConflict();

        $this->assertSame(10000, $appointment->refresh()->fee_cents);
        $this->assertSame('pending', $adjustment->refresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'payment_adjustment.approved']);
    }

    public function test_competing_refunds_are_revalidated_at_approval(): void
    {
        $this->travelTo('2026-11-08 12:00');
        $payment = Payment::factory()->create(['paid_at' => '2026-11-07']);
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        foreach (['75.00', '50.00'] as $amount) {
            $this->post(route('admin.payments.store', $payment->appointment), [...$this->refund($payment), 'amount' => $amount])->assertSessionHasNoErrors();
        }
        [$first, $second] = PaymentAdjustment::query()->orderBy('id')->get()->all();
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());
        $review = ['decision' => 'approved', 'reason' => 'Reviewed receipt'];

        $this->patch(route('admin.payment-adjustments.update', $first), $review)->assertSessionHasNoErrors();
        $this->patch(route('admin.payment-adjustments.update', $second), $review)->assertSessionHasErrors('amount');

        $this->assertSame(7500, (int) Payment::query()->where('kind', 'refund')->sum('amount_cents'));
        $this->assertSame('pending', $second->refresh()->status);
        $this->assertNull($second->reviewed_by);
    }

    public function test_requests_cannot_reference_another_appointments_payment(): void
    {
        $this->travelTo('2026-11-08 12:00');
        $payment = Payment::factory()->create(['paid_at' => '2026-11-07']);
        $other = Appointment::factory()->create();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());

        $this->post(route('admin.payments.store', $other), $this->refund($payment))->assertSessionHasErrors('amount');
        $this->assertDatabaseEmpty('payment_adjustments');
    }

    public function test_review_requires_authentication_mfa_valid_decision_and_reason(): void
    {
        $adjustment = PaymentAdjustment::factory()->create();
        $url = route('admin.payment-adjustments.update', $adjustment);
        $this->patch($url, [])->assertRedirect('/login');
        $this->actingAs(User::factory()->superAdmin()->create())->patch($url, [])->assertRedirect('/admin/security');
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());

        $this->patch($url, ['decision' => 'pending'])->assertSessionHasErrors(['decision', 'reason']);
        $this->assertSame('pending', $adjustment->refresh()->status);
    }

    public function test_review_queue_scopes_data_escapes_reasons_and_exposes_review_history(): void
    {
        $this->withoutVite();
        $appointment = Appointment::factory()->create(['fee_cents' => 10000]);
        $maker = User::factory()->admin()->withTwoFactor()->create();
        $adjustment = PaymentAdjustment::factory()->for($appointment)->create(['requested_by' => $maker->id, 'reason' => '<script>alert(1)</script>']);
        $synthetic = Appointment::factory()->create(['source' => 'synthetic']);
        $hidden = PaymentAdjustment::factory()->for($synthetic)->create();
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());

        $this->get(route('admin.payment-adjustments.index'))->assertSee($appointment->reference_number)
            ->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee($synthetic->reference_number)->assertSee('Approve request');
        $this->patch(route('admin.payment-adjustments.update', $hidden), ['decision' => 'approved', 'reason' => 'Cross dataset'])->assertNotFound();
        $this->patch(route('admin.payment-adjustments.update', $adjustment), ['decision' => 'approved', 'reason' => 'Approved after checking'])->assertSessionHasNoErrors();
        $this->get(route('admin.payment-adjustments.index', ['status' => 'approved']))->assertSee('Approved after checking');
        $this->verified($maker);
        $this->get(route('admin.appointments.show', $appointment))->assertSee('Approved after checking')->assertDontSee('Approve request');
    }

    /** @return array<string, mixed> */
    private function refund(Payment $payment): array
    {
        return ['idempotency_key' => (string) Str::uuid(), 'kind' => 'refund', 'amount' => '25.00',
            'original_payment_id' => $payment->id, 'method' => $payment->method, 'paid_at' => '2026-11-08T10:00', 'notes' => 'Refund requested'];
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }
}
