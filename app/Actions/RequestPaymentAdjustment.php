<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Support\OfficeAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RequestPaymentAdjustment
{
    /** @param array<string, mixed> $data */
    public function handle(Appointment $appointment, string $kind, array $data): PaymentAdjustment
    {
        Gate::authorize('payments.manage');

        return DB::transaction(function () use ($appointment, $kind, $data): PaymentAdjustment {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $key = $data['idempotency_key'];
            $reason = $kind === 'refund' ? $data['notes'] : $data['reason'];
            $payload = $kind === 'refund' ? $data : ['fee' => $data['fee'], 'version' => (int) $data['version']];
            $existing = PaymentAdjustment::query()->where('idempotency_key', $key)->first();
            if ($existing) {
                $comparison = $existing->payload;
                unset($comparison['previous_fee_cents']);
                abort_unless($existing->appointment_id === $locked->id && $existing->requested_by === auth()->id()
                    && $existing->kind === $kind && $existing->reason === $reason && $comparison == $payload,
                    409, 'This request was already submitted with different details. Refresh before submitting another request.');

                return $existing;
            }
            abort_if(Payment::query()->where('idempotency_key', $key)->exists(), 409);
            if ($kind === 'refund') {
                RecordPayment::validateRefund($locked, $data);
            } else {
                abort_unless($locked->version === (int) $data['version'], 409, 'The appointment has changed. Refresh before requesting a fee adjustment.');
                $payload['previous_fee_cents'] = $locked->fee_cents;
            }
            $adjustment = PaymentAdjustment::create([
                'appointment_id' => $locked->id, 'requested_by' => auth()->id(),
                'idempotency_key' => $key, 'kind' => $kind, 'payload' => $payload, 'reason' => $reason,
            ]);
            OfficeAudit::record('payment_adjustment.requested', $adjustment, [],
                $adjustment->only(['appointment_id', 'requested_by', 'kind', 'payload', 'status']), $reason);

            return $adjustment;
        });
    }
}
