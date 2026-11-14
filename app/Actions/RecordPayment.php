<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Support\OfficeAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPayment
{
    /** @param array<string, mixed> $data */
    public function handle(Appointment $appointment, array $data): Payment
    {
        return DB::transaction(function () use ($appointment, $data): Payment {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if ($data['kind'] === 'payment') {
                abort_if(PaymentAdjustment::query()->where('idempotency_key', $data['idempotency_key'])->exists(), 409);
            }
            $existing = Payment::query()->where('idempotency_key', $data['idempotency_key'])->first();
            $cents = self::cents($data['amount']);
            if ($existing) {
                $originalPaymentId = $data['kind'] === 'refund' ? (int) $data['original_payment_id'] : null;
                abort_unless($existing->appointment_id === $locked->id
                    && $existing->kind === $data['kind'] && $existing->amount_cents === $cents
                    && $existing->original_payment_id === $originalPaymentId
                    && $existing->method === $data['method']
                    && $existing->paid_at->format('Y-m-d\TH:i') === $data['paid_at']
                    && ($existing->reference ?? '') === ($data['reference'] ?? '')
                    && $existing->notes === $data['notes'], 409, 'This submission was already recorded with different details. Refresh the appointment before entering another payment or refund.');

                return $existing;
            }
            if ($data['kind'] === 'refund') {
                self::validateRefund($locked, $data);
            }
            $payment = $locked->payments()->create([
                'recorded_by' => auth()->id(), 'idempotency_key' => $data['idempotency_key'],
                'original_payment_id' => $data['kind'] === 'refund' ? $data['original_payment_id'] : null,
                'kind' => $data['kind'], 'amount_cents' => $cents, 'method' => $data['method'],
                'reference' => $data['reference'] ?? null, 'notes' => $data['notes'], 'paid_at' => $data['paid_at'],
            ]);
            $locked->load('payments');
            $locked->update([
                'payment_status' => match (true) {
                    $locked->fee_cents === 0 => 'not_required',
                    $locked->netPaidCents() <= 0 => 'unpaid',
                    $locked->balanceCents() === 0 => 'paid',
                    default => 'partial',
                },
            ]);
            OfficeAudit::record('payment.'.$data['kind'], $payment, [], $payment->only(['appointment_id', 'kind', 'amount_cents', 'method', 'paid_at', 'original_payment_id']), $data['notes']);

            return $payment;
        });
    }

    /** @param array<string, mixed> $data */
    public static function validateRefund(Appointment $appointment, array $data): void
    {
        $original = Payment::query()->where('appointment_id', $appointment->id)->where('kind', 'payment')->find($data['original_payment_id']);
        if ($original?->method === 'xendit_sandbox' && $data['method'] !== 'xendit_sandbox') {
            throw ValidationException::withMessages(['original_payment_id' => 'Choose Online Payment to record a refund for this test payment.']);
        }
        if ($original !== null && $original->method !== 'xendit_sandbox' && $data['method'] === 'xendit_sandbox') {
            throw ValidationException::withMessages(['method' => 'Test refunds must be linked to a test payment.']);
        }
        $refunded = Payment::query()->where('original_payment_id', $original?->id)->where('kind', 'refund')->sum('amount_cents');
        if (! $original || self::cents($data['amount']) > $original->amount_cents - $refunded || $data['paid_at'] < $original->paid_at->format('Y-m-d\TH:i')) {
            throw ValidationException::withMessages(['amount' => 'Refunds must reference a payment, follow its date, and not exceed its unrefunded amount.']);
        }
    }

    public static function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
