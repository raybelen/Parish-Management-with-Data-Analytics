<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAppointmentScheduleRequest;
use App\Models\Appointment;
use App\Support\OfficeAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class AppointmentScheduleController extends Controller
{
    public function update(UpdateAppointmentScheduleRequest $request, Appointment $appointment): RedirectResponse
    {
        DB::transaction(function () use ($request, $appointment): void {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless($locked->status === 'pending', 409, 'This appointment request can no longer be changed.');
            $before = $locked->only(['preferred_date', 'preferred_time']);
            $locked->fill($request->safe()->only(['preferred_date', 'preferred_time']));
            $locked->version++;
            $locked->save();
            OfficeAudit::record('appointment.preference_changed', $locked, $before, $locked->only(array_keys($before)), 'Requested by booking contact');
            OfficeAudit::notify($locked, 'rescheduled', 'Client changed preferred schedule: '.$locked->reference_number);
        });

        return redirect()->to(URL::temporarySignedRoute(
            'appointments.show',
            now()->addMinutes(30),
            ['appointment' => $appointment],
        ))->with('appointment_message', 'Your preferred date and time have been updated for parish review.');
    }
}
