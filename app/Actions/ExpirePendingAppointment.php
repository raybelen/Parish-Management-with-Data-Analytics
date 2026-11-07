<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Support\OfficeAudit;

class ExpirePendingAppointment
{
    public function __construct(private NotifyAppointmentClient $notifyClient) {}

    public function handle(Appointment $appointment): ?string
    {
        $expirationReason = $this->expirationReason($appointment);
        if ($appointment->status !== 'pending' || $expirationReason === null) {
            return null;
        }

        $outcome = $expirationReason === 'scheduled_start'
            ? 'The scheduled appointment time passed before physical document verification was completed.'
            : 'The physical document submission deadline passed before verification was completed.';

        $appointment->forceFill([
            'status' => 'expired',
            'outcome' => $outcome,
            'version' => $appointment->version + 1,
        ])->save();

        OfficeAudit::record('appointment.expired', $appointment, ['status' => 'pending'], ['status' => 'expired'], $outcome);
        OfficeAudit::notify($appointment, 'expired', 'Appointment hold expired: '.$appointment->reference_number);
        $this->notifyClient->handle($appointment, 'expired');

        return $expirationReason;
    }

    private function expirationReason(Appointment $appointment): ?string
    {
        $now = now();
        $documentDeadlinePassed = $appointment->documents_due_at?->lessThanOrEqualTo($now) ?? false;
        $scheduledStartPassed = $appointment->starts_at?->lessThanOrEqualTo($now) ?? false;

        if ($scheduledStartPassed
            && ($appointment->documents_due_at === null || $appointment->starts_at->lessThan($appointment->documents_due_at))) {
            return 'scheduled_start';
        }

        return $documentDeadlinePassed ? 'document_deadline' : null;
    }
}
