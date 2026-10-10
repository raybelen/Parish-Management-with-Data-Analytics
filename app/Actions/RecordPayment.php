<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\Payment;
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
            $existing = Payment::query()->where('idempotency_key', $data['idempotency_key'])->first();
            $cents = self::cents($data['amount']);
            if ($existing) {
                abort_unless($existing->appointment_id === $locked->id && $existing->kind === $data['kind'] && $existing->amount_cents === $cents, 409);

                return $existing;
            }
            if ($data['kind'] === 'refund') {
                $original = Payment::query()->where('appointment_id', $locked->id)->where('kind', 'payment')->find($data['original_payment_id']);
                $refunded = Payment::query()->where('original_payment_id', $original?->id)->where('kind', 'refund')->sum('amount_cents');
                if (! $original || $cents > $original->amount_cents - $refunded || $data['paid_at'] < $original->paid_at->format('Y-m-d\TH:i')) {
                    throw ValidationException::withMessages(['amount' => 'Refunds must reference a payment, follow its date, and not exceed its unrefunded amount.']);
                }
            }
            $payment = $locked->payments()->create([
                'recorded_by' => auth()->id(), 'idempotency_key' => $data['idempotency_key'],
                'original_payment_id' => $data['kind'] === 'refund' ? $data['original_payment_id'] : null,
                'kind' => $data['kind'], 'amount_cents' => $cents, 'method' => $data['method'],
                'reference' => $data['reference'] ?? null, 'notes' => $data['notes'], 'paid_at' => $data['paid_at'],
            ]);
            OfficeAudit::record('payment.'.$data['kind'], $payment, [], $payment->only(['appointment_id', 'kind', 'amount_cents', 'method', 'paid_at', 'original_payment_id']), $data['notes']);

            return $payment;
        });
    }

    public static function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
