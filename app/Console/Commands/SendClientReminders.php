<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminder;
use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SendClientReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Queue client email reminders for services and document deadlines within 24 hours';

    public function handle(): int
    {
        $now = now();
        Appointment::query()->where('source', 'operational')->where('preferred_contact_method', 'email')
            ->whereNotNull('client_email')->whereIn('status', ['pending', 'confirmed'])
            ->where(function (Builder $query) use ($now): void {
                $query->whereBetween('starts_at', [$now, $now->copy()->addDay()])
                    ->orWhereBetween('documents_due_at', [$now, $now->copy()->addDay()]);
            })->eachById(function (Appointment $appointment): void {
                $event = $appointment->status === 'confirmed' ? 'upcoming' : 'documents';
                $target = $event === 'upcoming' ? $appointment->starts_at : $appointment->documentVerificationDeadline();
                if (! $appointment->wantsEmailNotifications() || $target === null || ! $target->isFuture()
                    || $target->greaterThan(now()->addDay())
                    || ($event === 'documents' && $appointment->physicalDocumentsComplete())
                    || $appointment->{$event.'_reminder_sent_for'}?->equalTo($target)) {
                    return;
                }

                SendAppointmentReminder::dispatch($appointment->id, $event, $target->toDateTimeString());
            }, 250);

        $this->info('Client email reminders queued.');

        return self::SUCCESS;
    }
}
