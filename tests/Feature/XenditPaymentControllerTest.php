<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class XenditPaymentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_completed_checkout_is_recorded_once_and_reconciles_client_admin_and_revenue_views(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-11-01 16:00');
        $this->configureSandbox();
        Http::preventStrayRequests();
        Http::fake(['https://api.xendit.co/sessions' => Http::response($this->sessionResponse(), 201)]);
        $appointment = Appointment::factory()->create(['fee_cents' => 10000, 'payment_option' => 'online', 'payment_status' => 'partial']);
        Payment::factory()->for($appointment)->create(['amount_cents' => 4000]);
        $checkoutUrl = URL::temporarySignedRoute('appointments.payments.xendit', now()->addMinutes(5), ['appointment' => $appointment]);

        $this->post($checkoutUrl)->assertRedirect('https://checkout-staging.xendit.co/sessions/ps-sandbox-session-123');
        $this->post($checkoutUrl)->assertRedirect('https://checkout-staging.xendit.co/sessions/ps-sandbox-session-123');
        Http::assertSentCount(1);
        $transaction = PaymentTransaction::query()->sole();
        $this->assertSame(6000, $transaction->amount_cents);
        $this->assertSame('pending', $transaction->status);
        $this->assertSame(6000, $appointment->refresh()->balanceCents());
        $this->assertSame('partial', $appointment->payment_status);

        $payload = $this->completedWebhook($transaction);
        $this->withHeader('X-Callback-Token', 'sandbox-webhook-token')->postJson(route('webhooks.xendit.payment-sessions'), $payload)->assertNoContent();
        $this->postJson(route('webhooks.xendit.payment-sessions'), $payload)->assertNoContent();

        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseHas('payment_transactions', ['id' => $transaction->id, 'status' => 'paid', 'environment' => 'sandbox', 'payment_method' => 'PH_GCASH']);
        $transaction->refresh();
        $this->assertDatabaseHas('payments', ['id' => $transaction->ledger_payment_id, 'appointment_id' => $appointment->id, 'amount_cents' => 6000, 'reference' => 'py-sandbox-payment-123']);
        $this->assertSame('paid', $appointment->refresh()->payment_status);
        $this->assertSame(0, $appointment->balanceCents());
        $this->assertSame('pending', $appointment->status);

        $return = $this->get(route('appointments.payments.return', $transaction));
        $return->assertRedirect();
        $confirmation = $this->get($return->headers->get('Location'));
        $confirmation->assertSee('Paid')->assertSee('py-sandbox-payment-123')->assertSee('Demonstration statement:');
        $this->get($confirmation->viewData('billingUrl'))->assertSee('Paid')->assertSee('100.00')->assertSee('py-sandbox-payment-123');

        $admin = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($admin)->withSession(['admin.mfa_user_id' => $admin->id, 'admin.mfa_fingerprint' => $admin->adminSessionFingerprint()]);
        $this->get(route('admin.appointments.show', $appointment))->assertSee('Paid')->assertSee('PH_GCASH')->assertSee('ps-sandbox-session-123')->assertSee('py-sandbox-payment-123');
        $this->get(route('admin.revenue.index'))->assertViewHas('totals', ['received' => 10000, 'refunded' => 0, 'net' => 10000])
            ->assertViewHas('sandboxTotals', ['received' => 6000, 'refunded' => 0, 'net' => 6000])->assertViewHas('outstandingTotal', 0)
            ->assertSee('PH_GCASH')->assertSee('py-sandbox-payment-123')->assertSee('Sandbox');
    }

    public function test_expiring_an_older_checkout_does_not_unpay_a_settled_appointment(): void
    {
        $this->configureSandbox();
        $appointment = Appointment::factory()->create(['fee_cents' => 10000, 'payment_status' => 'paid']);
        Payment::factory()->for($appointment)->create(['method' => 'xendit_sandbox', 'amount_cents' => 10000]);
        $older = PaymentTransaction::factory()->for($appointment)->create();
        $payload = $this->completedWebhook($older);
        $payload['event'] = 'payment_session.expired';
        $payload['data']['status'] = 'EXPIRED';

        $this->withHeader('X-Callback-Token', 'sandbox-webhook-token')->postJson(route('webhooks.xendit.payment-sessions'), $payload)->assertNoContent();

        $this->assertSame('expired', $older->refresh()->status);
        $this->assertSame('paid', $appointment->refresh()->payment_status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_expiry_event_with_a_completed_status_is_rejected(): void
    {
        $this->configureSandbox();
        $transaction = PaymentTransaction::factory()->create();
        $payload = $this->completedWebhook($transaction);
        $payload['event'] = 'payment_session.expired';

        $this->withHeader('X-Callback-Token', 'sandbox-webhook-token')->postJson(route('webhooks.xendit.payment-sessions'), $payload)->assertUnprocessable();

        $this->assertSame('pending', $transaction->refresh()->status);
        $this->assertDatabaseEmpty('payments');
    }

    public function test_checkout_failure_keeps_partial_payment_and_records_failed_attempt(): void
    {
        $this->withoutVite();
        $this->configureSandbox();
        Http::preventStrayRequests();
        Http::fake(['https://api.xendit.co/sessions' => Http::response(['error_code' => 'TEMPORARY_ERROR'], 503)]);
        $appointment = Appointment::factory()->create(['fee_cents' => 10000, 'payment_option' => 'online', 'payment_status' => 'partial']);
        Payment::factory()->for($appointment)->create(['amount_cents' => 4000]);
        $url = URL::temporarySignedRoute('appointments.payments.xendit', now()->addMinutes(5), ['appointment' => $appointment]);

        $this->post($url)->assertStatus(503);

        $this->assertDatabaseHas('payment_transactions', ['appointment_id' => $appointment->id, 'status' => 'failed', 'amount_cents' => 6000, 'failure_code' => 'TEMPORARY_ERROR']);
        $this->assertSame('partial', $appointment->refresh()->payment_status);
        $this->assertSame(6000, $appointment->balanceCents());
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_changed_balance_creates_checkout_for_current_amount_instead_of_reusing_old_session(): void
    {
        $this->configureSandbox();
        Http::preventStrayRequests();
        Http::fake(['https://api.xendit.co/sessions' => Http::response($this->sessionResponse(), 201)]);
        $appointment = Appointment::factory()->create(['fee_cents' => 10000, 'payment_option' => 'online']);
        PaymentTransaction::factory()->for($appointment)->create(['amount_cents' => 10000, 'checkout_url' => 'https://checkout-staging.xendit.co/sessions/old-session']);
        Payment::factory()->for($appointment)->create(['amount_cents' => 4000]);
        $url = URL::temporarySignedRoute('appointments.payments.xendit', now()->addMinutes(5), ['appointment' => $appointment]);

        $this->post($url)->assertRedirect('https://checkout-staging.xendit.co/sessions/ps-sandbox-session-123');

        $this->assertDatabaseHas('payment_transactions', ['appointment_id' => $appointment->id, 'amount_cents' => 6000, 'provider_session_id' => 'ps-sandbox-session-123']);
        Http::assertSent(fn (Request $request): bool => $request['amount'] === 60);
    }

    public function test_signed_request_creates_a_sandbox_session_and_redirects_to_hosted_checkout(): void
    {
        $this->configureSandbox();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.xendit.co/sessions' => Http::response($this->sessionResponse(), 201),
        ]);
        $appointment = Appointment::factory()->create([
            'status' => 'pending',
            'fee_cents' => 15000,
            'payment_option' => 'online',
            'payment_status' => 'unpaid',
        ]);
        $url = URL::temporarySignedRoute('appointments.payments.xendit', now()->addMinutes(5), ['appointment' => $appointment]);

        $response = $this->post($url);

        $response->assertRedirect('https://checkout-staging.xendit.co/sessions/ps-sandbox-session-123');
        $transaction = PaymentTransaction::query()->sole();
        $this->assertSame('pending', $transaction->status);
        $this->assertSame(15000, $transaction->amount_cents);
        $this->assertSame('ps-sandbox-session-123', $transaction->provider_session_id);
        $this->assertSame('pending', $appointment->refresh()->payment_status);
        Http::assertSent(function (Request $request) use ($transaction): bool {
            return $request->url() === 'https://api.xendit.co/sessions'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('xnd_development_test_secret:'))
                && $request['reference_id'] === $transaction->reference_id
                && $request['amount'] === 150
                && $request['currency'] === 'PHP'
                && $request['country'] === 'PH'
                && $request['mode'] === 'PAYMENT_LINK'
                && $request['allow_save_payment_method'] === 'DISABLED'
                && ! isset($request['allowed_payment_channels']);
        });
    }

    public function test_unsigned_request_cannot_start_checkout(): void
    {
        $this->configureSandbox();
        Http::preventStrayRequests();
        $appointment = Appointment::factory()->create([
            'fee_cents' => 10000,
            'payment_option' => 'online',
        ]);

        $this->post(route('appointments.payments.xendit', $appointment))->assertForbidden();

        $this->assertDatabaseCount('payment_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_live_secret_is_rejected_without_sending_a_gateway_request(): void
    {
        $this->configureSandbox(secretKey: 'xnd_production_live_secret');
        Http::preventStrayRequests();
        $appointment = Appointment::factory()->create([
            'fee_cents' => 10000,
            'payment_option' => 'online',
            'payment_status' => 'unpaid',
        ]);
        $url = URL::temporarySignedRoute('appointments.payments.xendit', now()->addMinutes(5), ['appointment' => $appointment]);

        $this->post($url)
            ->assertServiceUnavailable()
            ->assertSee('Xendit Sandbox checkout could not be started');

        $this->assertDatabaseHas('payment_transactions', ['appointment_id' => $appointment->id, 'status' => 'failed']);
        $this->assertSame('failed', $appointment->refresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_live_checkout_url_is_rejected(): void
    {
        $this->configureSandbox();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.xendit.co/sessions' => Http::response([
                ...$this->sessionResponse(),
                'payment_link_url' => 'https://checkout.xendit.co/sessions/live-session',
            ], 201),
        ]);
        $appointment = Appointment::factory()->create([
            'fee_cents' => 10000,
            'payment_option' => 'online',
        ]);
        $url = URL::temporarySignedRoute('appointments.payments.xendit', now()->addMinutes(5), ['appointment' => $appointment]);

        $this->post($url)->assertServiceUnavailable();

        $this->assertDatabaseHas('payment_transactions', ['appointment_id' => $appointment->id, 'status' => 'failed']);
    }

    public function test_verified_completed_webhook_records_payment_once_and_updates_status(): void
    {
        $this->configureSandbox();
        $appointment = Appointment::factory()->create([
            'fee_cents' => 10000,
            'payment_option' => 'online',
            'payment_status' => 'pending',
        ]);
        $transaction = PaymentTransaction::factory()->for($appointment)->create([
            'amount_cents' => 10000,
            'provider_session_id' => 'ps-sandbox-session-123',
            'provider_business_id' => 'business-sandbox',
        ]);
        $payload = $this->completedWebhook($transaction);

        $this->withHeader('X-Callback-Token', 'sandbox-webhook-token')
            ->postJson(route('webhooks.xendit.payment-sessions'), $payload)
            ->assertNoContent();
        $this->withHeader('X-Callback-Token', 'sandbox-webhook-token')
            ->postJson(route('webhooks.xendit.payment-sessions'), $payload)
            ->assertNoContent();

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointment->id,
            'amount_cents' => 10000,
            'method' => 'xendit_sandbox',
            'reference' => 'py-sandbox-payment-123',
        ]);
        $transaction->refresh();
        $this->assertSame('paid', $transaction->status);
        $this->assertSame('PH_GCASH', $transaction->payment_method);
        $this->assertNotNull($transaction->ledger_payment_id);
        $this->assertSame('paid', $appointment->refresh()->payment_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.xendit_completed', 'entity_id' => $transaction->id]);
    }

    public function test_webhook_rejects_invalid_token_and_mismatched_amount(): void
    {
        $this->configureSandbox();
        $appointment = Appointment::factory()->create([
            'fee_cents' => 10000,
            'payment_option' => 'online',
            'payment_status' => 'pending',
        ]);
        $transaction = PaymentTransaction::factory()->for($appointment)->create([
            'amount_cents' => 10000,
            'provider_session_id' => 'ps-sandbox-session-123',
            'provider_business_id' => 'business-sandbox',
        ]);
        $payload = $this->completedWebhook($transaction);

        $this->withHeader('X-Callback-Token', 'wrong-token')
            ->postJson(route('webhooks.xendit.payment-sessions'), $payload)
            ->assertUnauthorized();
        $this->withHeader('X-Callback-Token', 'sandbox-webhook-token')
            ->postJson(route('webhooks.xendit.payment-sessions'), [
                ...$payload,
                'data' => [...$payload['data'], 'amount' => 99],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('pending', $transaction->refresh()->status);
        $this->assertSame('pending', $appointment->refresh()->payment_status);
    }

    public function test_verified_xendit_dashboard_probe_is_acknowledged_without_recording_payment(): void
    {
        $this->configureSandbox();

        $this->withHeader('X-Callback-Token', 'sandbox-webhook-token')
            ->postJson(route('webhooks.xendit.payment-sessions'), [
                'event' => 'payment_session.completed',
                'business_id' => '5781d19b2e2385880609791c',
                'created' => '2020-04-20T16:25:52Z',
                'data' => [
                    'id' => 'ps-579c8d61f23fa4ca35e52da4',
                    'reference_id' => 'test_session',
                    'currency' => 'IDR',
                    'session_type' => 'SAVE',
                    'status' => 'COMPLETED',
                    'updated' => '2020-04-20T16:25:52Z',
                ],
            ])
            ->assertNoContent();

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_verified_expiry_webhook_marks_unpaid_session_expired(): void
    {
        $this->configureSandbox();
        $appointment = Appointment::factory()->create([
            'fee_cents' => 10000,
            'payment_option' => 'online',
            'payment_status' => 'pending',
        ]);
        $transaction = PaymentTransaction::factory()->for($appointment)->create([
            'amount_cents' => 10000,
            'provider_session_id' => 'ps-sandbox-session-123',
            'provider_business_id' => 'business-sandbox',
        ]);
        $payload = $this->completedWebhook($transaction);
        $payload['event'] = 'payment_session.expired';
        $payload['data']['status'] = 'EXPIRED';
        unset($payload['data']['payment_id']);

        $this->withHeader('X-Callback-Token', 'sandbox-webhook-token')
            ->postJson(route('webhooks.xendit.payment-sessions'), $payload)
            ->assertNoContent();

        $this->assertSame('expired', $transaction->refresh()->status);
        $this->assertSame('expired', $appointment->refresh()->payment_status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_return_page_is_private_and_does_not_treat_browser_return_as_payment(): void
    {
        $this->configureSandbox();
        $appointment = Appointment::factory()->create([
            'fee_cents' => 10000,
            'payment_option' => 'online',
            'payment_status' => 'pending',
        ]);
        $transaction = PaymentTransaction::factory()->for($appointment)->create(['status' => 'pending']);

        $this->get(route('appointments.payments.return', $transaction))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertSee('Payment confirmation is processing')
            ->assertSee('window.setTimeout(() => window.location.reload(), 3000);', false)
            ->assertSee($appointment->reference_number)
            ->assertDontSee($appointment->client_email);

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('pending', $transaction->refresh()->status);
    }

    public function test_paid_return_redirects_to_manage_appointment_with_paid_status(): void
    {
        $this->configureSandbox();
        $appointment = Appointment::factory()->create([
            'fee_cents' => 10000,
            'payment_option' => 'online',
            'payment_status' => 'paid',
        ]);
        $transaction = PaymentTransaction::factory()->for($appointment)->create([
            'status' => 'paid',
            'provider_payment_id' => 'py-sandbox-payment-123',
            'paid_at' => now(),
        ]);

        $response = $this->get(route('appointments.payments.return', $transaction));

        $response
            ->assertRedirectContains(route('appointments.show', $appointment))
            ->assertSessionHas('appointment_message', 'Sandbox payment confirmed. Test payment status is now Paid; no real money was collected.');

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Sandbox payment confirmed. Test payment status is now Paid; no real money was collected.')
            ->assertSee('Paid');
    }

    private function configureSandbox(string $secretKey = 'xnd_development_test_secret'): void
    {
        config([
            'app.url' => 'https://parish.test',
            'services.xendit.sandbox_enabled' => true,
            'services.xendit.sandbox_secret_key' => $secretKey,
            'services.xendit.sandbox_webhook_token' => 'sandbox-webhook-token',
            'services.xendit.sandbox_business_id' => 'business-sandbox',
        ]);
        URL::forceRootUrl('https://parish.test');
        URL::forceScheme('https');
    }

    /** @return array<string, mixed> */
    private function sessionResponse(): array
    {
        return [
            'payment_session_id' => 'ps-sandbox-session-123',
            'payment_link_url' => 'https://checkout-staging.xendit.co/sessions/ps-sandbox-session-123',
            'business_id' => 'business-sandbox',
            'expires_at' => now()->addMinutes(30)->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function completedWebhook(PaymentTransaction $transaction): array
    {
        return [
            'event' => 'payment_session.completed',
            'business_id' => 'business-sandbox',
            'created' => '2026-11-01T08:00:00Z',
            'data' => [
                'reference_id' => $transaction->reference_id,
                'payment_session_id' => $transaction->provider_session_id,
                'payment_id' => 'py-sandbox-payment-123',
                'amount' => intdiv($transaction->amount_cents, 100),
                'currency' => 'PHP',
                'status' => 'COMPLETED',
                'updated' => '2026-11-01T08:00:00Z',
                'channel_code' => 'PH_GCASH',
            ],
        ];
    }
}
