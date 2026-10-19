<?php

namespace App\Http\Controllers;

use App\Actions\ReserveAppointmentSlot;
use App\Http\Requests\UpdateAppointmentScheduleRequest;
use App\Models\Appointment;
use App\Support\AppointmentAccess;
use App\Support\OfficeAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AppointmentScheduleController extends Controller
{
    public function update(UpdateAppointmentScheduleRequest $request, Appointment $appointment, ReserveAppointmentSlot $reserveAppointmentSlot, AppointmentAccess $access): RedirectResponse
    {
        $reserved = DB::transaction(function () use ($request, $appointment, $reserveAppointmentSlot): bool {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless($locked->status === 'pending', 409, 'This appointment request can no longer be changed.');
            $before = $locked->only(['preferred_date', 'preferred_time', 'starts_at', 'ends_at', 'reserved_until', 'confirmed_church']);
            $locked->fill($request->safe()->only(['preferred_date', 'preferred_time']));
            $locked->version++;
            $locked->save();
            $reserved = $reserveAppointmentSlot->handle($locked);
            $locked->refresh();
            OfficeAudit::record('appointment.preference_changed', $locked, $before, $locked->only(array_keys($before)), 'Requested by booking contact');
            OfficeAudit::notify($locked, 'rescheduled', 'Client changed preferred schedule: '.$locked->reference_number);

            return $reserved;
        });

        return redirect()->to($access->issue($request, $appointment))->with(
            'appointment_message',
            $reserved
                ? 'Your new date, time, and venue have been reserved. The appointment remains pending document and payment verification.'
                : 'Your preferred date and time were updated, but that slot could not be reserved. The parish office will review an alternative schedule.',
        );
    }
}
