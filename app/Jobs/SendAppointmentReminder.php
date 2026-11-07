<?php

namespace App\Jobs;

use App\Mail\AppointmentUpdate;
use App\Models\Appointment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendAppointmentReminder implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $appointmentId, public string $event, public string $target)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->appointmentId.':'.$this->event.':'.$this->target;
    }

    public function handle(): void
    {
        DB::transaction(function (): void {
            $appointment = Appointment::query()->lockForUpdate()->find($this->appointmentId);
            if (! $appointment || $appointment->source !== 'operational' || ! $appointment->wantsEmailNotifications()) {
                return;
            }

            $target = match ($this->event) {
                'upcoming' => $appointment->status === 'confirmed' ? $appointment->starts_at : null,
                'documents' => $appointment->status === 'pending' && ! $appointment->physicalDocumentsComplete()
                    ? $appointment->documentVerificationDeadline() : null,
            };
            $trackingColumn = $this->event.'_reminder_sent_for';
            if ($target === null || $target->toDateTimeString() !== $this->target
                || ! $target->isFuture() || $target->greaterThan(now()->addDay())
                || $appointment->{$trackingColumn}?->equalTo($target)) {
                return;
            }

            Mail::to($appointment->client_email)->sendNow(new AppointmentUpdate($appointment, $this->event));
            $appointment->forceFill([$trackingColumn => $target])->save();
        });
    }
}
