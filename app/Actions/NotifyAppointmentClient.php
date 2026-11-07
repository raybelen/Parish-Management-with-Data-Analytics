<?php

namespace App\Actions;

use App\Mail\AppointmentUpdate;
use App\Models\Appointment;
use Illuminate\Support\Facades\Mail;

class NotifyAppointmentClient
{
    public function handle(Appointment $appointment, string $event): void
    {
        if ($appointment->source !== 'operational' || ! $appointment->wantsEmailNotifications()) {
            return;
        }

        Mail::to($appointment->client_email)->queue(new AppointmentUpdate($appointment, $event));
    }
}
