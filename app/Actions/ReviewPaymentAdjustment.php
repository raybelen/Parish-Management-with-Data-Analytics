<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\PaymentAdjustment;
use App\Models\User;
use App\Support\OfficeAccess;
use App\Support\OfficeAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReviewPaymentAdjustment
{
    public function __construct(private RecordPayment $recordPayment) {}

    public function handle(PaymentAdjustment $adjustment, string $decision, string $reason): void
    {
        Gate::authorize('payments.approve');
        DB::transaction(function () use ($adjustment, $decision, $reason): void {
            $reviewer = User::query()->sharedLock()->findOrFail(auth()->id());
            abort_unless($reviewer->role === User::ROLE_SUPER_ADMIN, 403);
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($adjustment->appointment_id);
            OfficeAccess::appointment($reviewer, $appointment);
            $locked = PaymentAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
            abort_if($locked->requested_by === $reviewer->id, 403, 'A different super administrator must review your request.');
            abort_unless($locked->status === 'pending', 409, 'This request has already been reviewed.');
            if ($decision === 'approved') {
                if ($locked->kind === 'refund') {
                    $payment = $this->recordPayment->handle($appointment, $locked->payload);
                    $locked->payment_id = $payment->id;
                } else {
                    abort_unless($appointment->version === $locked->payload['version']
                        && $appointment->fee_cents === $locked->payload['previous_fee_cents'],
                        409, 'The appointment has changed. Reject this request and ask for a new fee adjustment.');
                    $before = $appointment->fee_cents;
                    $appointment->fee_cents = RecordPayment::cents($locked->payload['fee']);
                    $appointment->load('payments');
                    $appointment->payment_status = match (true) {
                        $appointment->fee_cents === 0 => 'not_required',
                        $appointment->netPaidCents() <= 0 => 'unpaid',
                        $appointment->balanceCents() === 0 => 'paid',
                        default => 'partial',
                    };
                    $appointment->version++;
                    $appointment->save();
                    OfficeAudit::record('appointment.fee_changed', $appointment, ['fee_cents' => $before],
                        ['fee_cents' => $appointment->fee_cents, 'adjustment_id' => $locked->id], $reason);
                }
            }
            $locked->status = $decision;
            $locked->reviewed_by = $reviewer->id;
            $locked->review_reason = $reason;
            $locked->reviewed_at = now();
            $locked->save();
            OfficeAudit::record('payment_adjustment.'.$decision, $locked, ['status' => 'pending'],
                $locked->only(['appointment_id', 'requested_by', 'reviewed_by', 'status', 'payment_id']), $reason);
        });
    }
}
