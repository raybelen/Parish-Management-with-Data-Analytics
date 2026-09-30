<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAppointmentScheduleRequest;
use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;

class AppointmentScheduleController extends Controller
{
    public function update(UpdateAppointmentScheduleRequest $request, Appointment $appointment): RedirectResponse
    {
        $updated = Appointment::query()
            ->whereKey($appointment->getKey())
            ->where('status', 'pending')
            ->update($request->safe()->only(['preferred_date', 'preferred_time']));

        abort_if($updated !== 1, 409, 'This appointment request can no longer be changed.');

        return redirect()->to(URL::temporarySignedRoute(
            'appointments.show',
            now()->addMinutes(30),
            ['appointment' => $appointment],
        ))->with('appointment_message', 'Your preferred date and time have been updated for parish review.');
    }
}
