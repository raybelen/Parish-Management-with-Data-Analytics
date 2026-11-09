<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class ParishBillingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_booking_displays_current_fee_before_submission(): void
    {
        $this->withoutVite();
        ServiceType::query()->where('slug', 'baptism')->update(['fee_cents' => 12345]);

        $this->get('/appointments/new')->assertSee('123.45')->assertSee('data-fee-cents="12345"', false)
            ->assertSee('Payment is not required to confirm your appointment')->assertSee('data-review-fee', false);
    }

    public function test_private_statement_requires_signature_session_and_single_use_access(): void
    {
        $this->withoutVite();
        $appointment = Appointment::factory()->create(['fee_cents' => 100000]);
        Payment::factory()->for($appointment)->create(['amount_cents' => 40000, 'reference' => 'REAL-RECEIPT', 'notes' => 'PRIVATE STAFF NOTE']);
        Payment::factory()->for($appointment)->create(['amount_cents' => 60000, 'method' => 'xendit_sandbox', 'reference' => 'TEST-REFERENCE']);
        $url = URL::temporarySignedRoute('appointments.billing', now()->addMinutes(5), ['appointment' => $appointment, 'access_token' => 'billing-token']);
        $this->get(route('appointments.billing', $appointment))->assertForbidden();
        $this->get($url)->assertRedirect(route('appointments.lookup.create'));

        $this->withSession(['appointment_access_tokens' => ['billing-token' => $appointment->id]])->get($url)
            ->assertSee('Billing statement')->assertSee('400.00')->assertSee('600.00')->assertSee('REAL-RECEIPT')
            ->assertSee('Paid')->assertSee('TEST-REFERENCE')->assertSee('Demonstration statement:')->assertDontSee('PRIVATE STAFF NOTE')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->get($url)->assertRedirect(route('appointments.lookup.create'));
    }

    public function test_appointment_page_links_to_private_statement_and_shows_actual_history(): void
    {
        $this->withoutVite();
        $appointment = Appointment::factory()->create(['fee_cents' => 100000]);
        Payment::factory()->for($appointment)->create(['amount_cents' => 40000, 'reference' => '<script>bad()</script>']);
        $url = URL::temporarySignedRoute('appointments.show', now()->addMinutes(5), ['appointment' => $appointment, 'access_token' => 'view-token']);

        $response = $this->withSession(['appointment_access_tokens' => ['view-token' => $appointment->id]])->get($url);

        $response->assertSee('View / print billing statement')->assertSee('600.00')->assertSee('<script>bad()</script>')->assertDontSee('<script>bad()</script>', false);
        $this->get($response->viewData('billingUrl'))->assertSee('Billing statement');
    }

    public function test_staff_statement_enforces_finance_permission_and_dataset_scope(): void
    {
        $this->withoutVite();
        $appointment = Appointment::factory()->create();
        $url = route('admin.appointments.billing', $appointment);
        $this->get($url)->assertRedirect('/login');
        $this->verified(User::factory()->state(['role' => 'priest'])->withTwoFactor()->create())->get($url)->assertForbidden();
        $this->verified(User::factory()->admin()->withTwoFactor()->create())->get($url)->assertSee('Billing statement');
        $demo = Appointment::factory()->create(['source' => 'synthetic']);
        $this->get(route('admin.appointments.billing', $demo))->assertNotFound();
    }

    public function test_real_refund_of_sandbox_payment_is_rejected_without_ledger_change(): void
    {
        $this->travelTo('2026-11-06 12:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create(['fee_cents' => 10000]);
        $payment = Payment::factory()->for($appointment)->create(['method' => 'xendit_sandbox', 'paid_at' => '2026-11-05']);

        $this->post(route('admin.payments.store', $appointment), [
            'idempotency_key' => (string) Str::uuid(), 'kind' => 'refund', 'amount' => '100.00',
            'original_payment_id' => $payment->id, 'method' => 'cash', 'paid_at' => '2026-11-06T10:00', 'notes' => 'Invalid refund',
        ])->assertSessionHasErrors('original_payment_id');

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_statement_shows_refunds_overpayment_and_fee_waiver(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create(['fee_cents' => 10000]);
        $payment = Payment::factory()->for($appointment)->create(['amount_cents' => 20000]);
        Payment::factory()->for($appointment)->create(['original_payment_id' => $payment->id, 'kind' => 'refund', 'amount_cents' => 5000]);

        $this->get(route('admin.appointments.billing', $appointment))->assertSee('Overpayment:')->assertSee('50.00')->assertSee('Refund #');
        $waived = Appointment::factory()->create(['fee_cents' => 0]);
        $this->get(route('admin.appointments.billing', $waived))->assertSee('No payment due');
    }

    public function test_walk_in_and_sandbox_payments_share_one_balance_without_changing_appointment_status(): void
    {
        $this->travelTo('2026-11-06 12:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create(['fee_cents' => 10000, 'status' => 'pending', 'payment_status' => 'paid']);
        Payment::factory()->for($appointment)->create(['method' => 'xendit_sandbox', 'amount_cents' => 6000]);

        $this->post(route('admin.payments.store', $appointment), [
            'idempotency_key' => (string) Str::uuid(), 'kind' => 'payment', 'amount' => '40.00',
            'method' => 'cash', 'paid_at' => '2026-11-06T10:00', 'notes' => 'Actual payment received',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'payment_status' => 'paid', 'status' => 'pending']);
        $this->assertSame(0, $appointment->refresh()->balanceCents());
    }

    public function test_statement_rejects_expired_links_and_another_appointments_session_token(): void
    {
        $appointment = Appointment::factory()->create();
        $other = Appointment::factory()->create();
        $this->withSession(['appointment_access_tokens' => ['token' => $other->id]]);
        $url = URL::temporarySignedRoute('appointments.billing', now()->addMinutes(5), ['appointment' => $appointment, 'access_token' => 'token']);
        $this->get($url)->assertRedirect(route('appointments.lookup.create'));
        $expired = URL::temporarySignedRoute('appointments.billing', now()->subMinute(), ['appointment' => $appointment, 'access_token' => 'token']);
        $this->get($expired)->assertForbidden();
    }

    public function test_demo_refund_updates_balance_once_and_cannot_create_manual_gateway_payments(): void
    {
        $this->travelTo('2026-11-06 12:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create(['fee_cents' => 10000, 'payment_status' => 'paid']);
        $payment = Payment::factory()->for($appointment)->create(['method' => 'xendit_sandbox', 'amount_cents' => 10000, 'paid_at' => '2026-11-05']);
        $payload = ['idempotency_key' => (string) Str::uuid(), 'kind' => 'refund', 'amount' => '25.00', 'original_payment_id' => $payment->id,
            'method' => 'xendit_sandbox', 'paid_at' => '2026-11-06T10:00', 'notes' => 'Demonstration refund'];

        $this->post(route('admin.payments.store', $appointment), $payload)->assertSessionHasNoErrors();
        $this->post(route('admin.payments.store', $appointment), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('payments', 1);
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create())
            ->patch(route('admin.payment-adjustments.update', PaymentAdjustment::query()->sole()), ['decision' => 'approved', 'reason' => 'Demo refund reviewed'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('payments', ['appointment_id' => $appointment->id, 'kind' => 'refund', 'method' => 'xendit_sandbox', 'amount_cents' => 2500]);
        $this->assertSame(2500, $appointment->refresh()->balanceCents());
        $this->assertSame('partial', $appointment->payment_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.refund']);

        $this->post(route('admin.payments.store', $appointment), [...$payload, 'kind' => 'payment', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('method');
        $this->post(route('admin.payments.store', $appointment), [...$payload, 'amount' => '76.00', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_demo_refunds_cannot_reduce_walk_in_collections(): void
    {
        $this->travelTo('2026-11-06 12:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $payment = Payment::factory()->create(['method' => 'cash', 'paid_at' => '2026-11-05']);

        $this->post(route('admin.payments.store', $payment->appointment), [
            'idempotency_key' => (string) Str::uuid(), 'kind' => 'refund', 'amount' => '25.00', 'original_payment_id' => $payment->id,
            'method' => 'xendit_sandbox', 'paid_at' => '2026-11-06T10:00', 'notes' => 'Incorrect refund method',
        ])->assertSessionHasErrors('method');

        $this->assertDatabaseCount('payments', 1);
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }

    public function test_reused_submission_key_rejects_changed_payment_details(): void
    {
        $this->travelTo('2026-11-07 12:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create(['fee_cents' => 10000]);
        $payload = ['idempotency_key' => (string) Str::uuid(), 'kind' => 'payment', 'amount' => '25.00',
            'method' => 'cash', 'paid_at' => '2026-11-07T10:00', 'reference' => 'RECEIPT-1', 'notes' => 'Payment received'];
        $url = route('admin.payments.store', $appointment);

        $this->post($url, $payload)->assertSessionHasNoErrors();
        foreach (['method' => 'gcash', 'paid_at' => '2026-11-07T11:00', 'reference' => 'RECEIPT-2', 'notes' => 'Different entry'] as $field => $value) {
            $this->post($url, [...$payload, $field => $value])->assertConflict();
        }

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['method' => 'cash', 'reference' => 'RECEIPT-1', 'notes' => 'Payment received']);
        $this->assertSame(7500, $appointment->refresh()->balanceCents());
    }

    public function test_refund_retry_cannot_silently_switch_the_original_payment(): void
    {
        $this->travelTo('2026-11-07 12:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create();
        $payments = Payment::factory()->count(2)->for($appointment)->create(['paid_at' => '2026-11-06', 'amount_cents' => 10000]);
        $payload = ['idempotency_key' => (string) Str::uuid(), 'kind' => 'refund', 'amount' => '25.00',
            'original_payment_id' => $payments[0]->id, 'method' => 'cash', 'paid_at' => '2026-11-07T10:00', 'notes' => 'Refund'];

        $this->post(route('admin.payments.store', $appointment), $payload)->assertSessionHasNoErrors();
        $this->post(route('admin.payments.store', $appointment), [...$payload, 'original_payment_id' => $payments[1]->id])->assertConflict();

        $this->assertDatabaseCount('payments', 2);
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create())
            ->patch(route('admin.payment-adjustments.update', PaymentAdjustment::query()->sole()), ['decision' => 'approved', 'reason' => 'Original receipt verified'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payments', 3);
        $this->assertDatabaseHas('payments', ['kind' => 'refund', 'original_payment_id' => $payments[0]->id, 'amount_cents' => 2500]);
    }

    public function test_fee_waiver_keeps_retained_payments_visibly_overpaid(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create(['fee_cents' => 10000]);
        Payment::factory()->for($appointment)->create(['amount_cents' => 10000]);

        $this->patch(route('admin.payments.fee', $appointment), ['idempotency_key' => (string) Str::uuid(), 'fee' => '0', 'version' => 1, 'reason' => 'Fee waived'])
            ->assertSessionHasNoErrors();

        $this->assertSame(10000, $appointment->refresh()->fee_cents);
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create())
            ->patch(route('admin.payment-adjustments.update', PaymentAdjustment::query()->sole()), ['decision' => 'approved', 'reason' => 'Waiver authorized'])->assertSessionHasNoErrors();
        $this->assertSame('Overpaid', $appointment->refresh()->billingStatusLabel());
        $this->get(route('admin.appointments.billing', $appointment))->assertSee('Overpaid')->assertSee('Overpayment:')->assertSee('100.00');
    }

    public function test_only_client_statements_use_the_private_history_guard(): void
    {
        $this->withoutVite();
        $appointment = Appointment::factory()->create();
        $url = URL::temporarySignedRoute('appointments.billing', now()->addMinutes(5), ['appointment' => $appointment, 'access_token' => 'billing-token']);

        $this->withSession(['appointment_access_tokens' => ['billing-token' => $appointment->id]])->get($url)
            ->assertSee('data-one-time-appointment-access', false)
            ->assertSee('data-manage-url="'.route('appointments.lookup.create').'"', false)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->verified(User::factory()->admin()->withTwoFactor()->create())->get(route('admin.appointments.billing', $appointment))
            ->assertDontSee('data-one-time-appointment-access', false);
    }
}
