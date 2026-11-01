<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class XenditPaymentGateway
{
    public static function isConfigured(): bool
    {
        $secretKey = (string) config('services.xendit.sandbox_secret_key');
        $webhookToken = (string) config('services.xendit.sandbox_webhook_token');

        return (bool) config('services.xendit.sandbox_enabled')
            && str_starts_with($secretKey, 'xnd_development_')
            && $webhookToken !== '';
    }

    /**
     * @return array{payment_session_id: string, payment_link_url: string, business_id: string, expires_at: string}
     */
    public function createSession(PaymentTransaction $transaction, string $returnUrl): array
    {
        $this->assertSandboxConfiguration();

        if (! str_starts_with($returnUrl, 'https://')) {
            throw new RuntimeException('Xendit Sandbox return URLs require an HTTPS APP_URL.');
        }

        $appointment = $transaction->appointment;
        $customerName = Str::of(Str::ascii($appointment->client_full_name))
            ->replaceMatches('/[^A-Za-z0-9 ]+/', '')
            ->squish()
            ->limit(100, '')
            ->toString();
        $customer = [
            'reference_id' => 'cust'.str_replace('-', '', $transaction->public_id),
            'type' => 'INDIVIDUAL',
            'individual_detail' => [
                'given_names' => $customerName !== '' ? $customerName : 'Parishioner',
            ],
        ];

        if ($appointment->client_email !== null && mb_strlen($appointment->client_email) <= 50) {
            $customer['email'] = $appointment->client_email;
        }

        $response = Http::baseUrl((string) config('services.xendit.api_url'))
            ->withBasicAuth((string) config('services.xendit.sandbox_secret_key'), '')
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->post('/sessions', [
                'reference_id' => $transaction->reference_id,
                'customer' => $customer,
                'session_type' => 'PAY',
                'mode' => 'PAYMENT_LINK',
                'allow_save_payment_method' => 'DISABLED',
                'capture_method' => 'AUTOMATIC',
                'amount' => $transaction->amount_cents,
                'currency' => 'PHP',
                'country' => 'PH',
                'locale' => 'en',
                'description' => 'Sandbox payment for appointment '.$appointment->reference_number,
                'success_return_url' => $returnUrl,
                'cancel_return_url' => $returnUrl,
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
                'metadata' => [
                    'appointment_reference' => $appointment->reference_number,
                    'environment' => 'sandbox',
                ],
            ])
            ->throw();

        $data = $response->json();
        $sessionId = $data['payment_session_id'] ?? null;
        $checkoutUrl = $data['payment_link_url'] ?? null;
        $businessId = $data['business_id'] ?? null;
        $expiresAt = $data['expires_at'] ?? null;

        if (! is_string($sessionId) || ! str_starts_with($sessionId, 'ps-')
            || ! is_string($checkoutUrl) || ! is_string($businessId) || ! is_string($expiresAt)) {
            throw new RuntimeException('Xendit Sandbox returned an incomplete payment session.');
        }

        $host = mb_strtolower((string) parse_url($checkoutUrl, PHP_URL_HOST));
        $allowedHosts = config('services.xendit.sandbox_checkout_hosts', []);
        if (! str_starts_with($checkoutUrl, 'https://') || ! in_array($host, $allowedHosts, true)) {
            throw new RuntimeException('Xendit returned a non-sandbox checkout URL.');
        }

        $configuredBusinessId = (string) config('services.xendit.sandbox_business_id');
        if ($configuredBusinessId !== '' && ! hash_equals($configuredBusinessId, $businessId)) {
            throw new RuntimeException('Xendit Sandbox returned an unexpected business account.');
        }

        return [
            'payment_session_id' => $sessionId,
            'payment_link_url' => $checkoutUrl,
            'business_id' => $businessId,
            'expires_at' => $expiresAt,
        ];
    }

    private function assertSandboxConfiguration(): void
    {
        if (! (bool) config('services.xendit.sandbox_enabled')) {
            throw new RuntimeException('Xendit Sandbox payments are disabled.');
        }

        $secretKey = (string) config('services.xendit.sandbox_secret_key');
        if (! str_starts_with($secretKey, 'xnd_development_')) {
            throw new RuntimeException('Only Xendit development credentials are accepted.');
        }

        if ((string) config('services.xendit.sandbox_webhook_token') === '') {
            throw new RuntimeException('The Xendit Sandbox webhook token is not configured.');
        }
    }
}
