<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class XenditPaymentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

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
                && $request['amount'] === 15000
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
                'data' => [...$payload['data'], 'amount' => 9999],
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
            ->assertSee($appointment->reference_number)
            ->assertDontSee($appointment->client_email);

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('pending', $transaction->refresh()->status);
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
                'amount' => $transaction->amount_cents,
                'currency' => 'PHP',
                'status' => 'COMPLETED',
                'updated' => '2026-11-01T08:00:00Z',
                'channel_code' => 'PH_GCASH',
            ],
        ];
    }
}
