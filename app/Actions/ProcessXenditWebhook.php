<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Support\OfficeAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessXenditWebhook
{
    /** @param array<string, mixed> $payload */
    public function handle(array $payload): void
    {
        DB::transaction(function () use ($payload): void {
            $data = $payload['data'];
            $transaction = PaymentTransaction::query()
                ->where('reference_id', $data['reference_id'])
                ->lockForUpdate()
                ->first();

            if ($transaction === null) {
                Log::warning('Ignored Xendit Sandbox webhook for an unknown payment reference.');

                return;
            }

            $providerAmountCents = (int) $data['amount'] * 100;

            abort_unless($transaction->environment === 'sandbox'
                && hash_equals((string) $transaction->provider_session_id, (string) $data['payment_session_id'])
                && hash_equals((string) $transaction->provider_business_id, (string) $payload['business_id'])
                && $transaction->amount_cents === $providerAmountCents
                && $transaction->currency === $data['currency'], 422);

            $eventAt = CarbonImmutable::parse($data['updated'] ?? $payload['created']);

            if ($payload['event'] === 'payment_session.expired') {
                abort_unless($data['status'] === 'EXPIRED', 422);
                if ($transaction->status !== 'paid') {
                    $transaction->update([
                        'status' => 'expired',
                        'last_event_at' => $eventAt,
                    ]);
                    $appointment = Appointment::query()->lockForUpdate()->findOrFail($transaction->appointment_id);
                    $appointment->load('payments');
                    $appointment->update([
                        'payment_status' => match (true) {
                            $appointment->fee_cents === 0 => 'not_required',
                            $appointment->balanceCents() === 0 => 'paid',
                            $appointment->netPaidCents() > 0 => 'partial',
                            $appointment->paymentTransactions()->where('status', 'pending')->where('expires_at', '>', now())->exists() => 'pending',
                            default => 'expired',
                        },
                    ]);
                }

                return;
            }

            abort_unless($data['status'] === 'COMPLETED', 422);
            if ($transaction->status === 'paid') {
                return;
            }

            $appointment = Appointment::query()->lockForUpdate()->findOrFail($transaction->appointment_id);
            $existingPayment = Payment::query()->where('idempotency_key', $transaction->public_id)->first();
            if ($existingPayment !== null) {
                abort_unless($existingPayment->appointment_id === $appointment->id
                    && $existingPayment->amount_cents === $transaction->amount_cents
                    && hash_equals((string) $existingPayment->reference, (string) $data['payment_id']), 409);
                $payment = $existingPayment;
            } else {
                $payment = $appointment->payments()->create([
                    'idempotency_key' => $transaction->public_id,
                    'kind' => 'payment',
                    'amount_cents' => $transaction->amount_cents,
                    'method' => 'xendit_sandbox',
                    'reference' => $data['payment_id'],
                    'notes' => 'Online test payment confirmed automatically.',
                    'paid_at' => $eventAt,
                ]);
            }

            $paymentMethod = filled($data['channel_code'] ?? null)
                ? Str::limit((string) $data['channel_code'], 60, '')
                : 'XENDIT_SANDBOX';
            $transaction->update([
                'ledger_payment_id' => $payment->id,
                'provider_payment_id' => $data['payment_id'],
                'payment_method' => $paymentMethod,
                'status' => 'paid',
                'paid_at' => $eventAt,
                'last_event_at' => $eventAt,
            ]);

            $appointment->load('payments');
            $appointment->update([
                'payment_status' => $appointment->balanceCents() === 0 ? 'paid' : 'partial',
            ]);
            OfficeAudit::record('payment.xendit_completed', $transaction, [], [
                'appointment_id' => $appointment->id,
                'amount_cents' => $transaction->amount_cents,
                'environment' => 'sandbox',
                'provider_payment_id' => $data['payment_id'],
            ]);
        });
    }
}
