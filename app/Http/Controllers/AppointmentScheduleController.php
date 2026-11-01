<?php

namespace App\Http\Controllers;

use App\Actions\ReserveAppointmentSlot;
use App\Http\Requests\UpdateAppointmentScheduleRequest;
use App\Models\Appointment;
use App\Models\ParishSetting;
use App\Support\AppointmentAccess;
use App\Support\OfficeAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentScheduleController extends Controller
{
    public function update(UpdateAppointmentScheduleRequest $request, Appointment $appointment, ReserveAppointmentSlot $reserveAppointmentSlot, AppointmentAccess $access): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $appointment, $reserveAppointmentSlot): void {
                ParishSetting::query()->where('key', 'office')->lockForUpdate()->firstOrFail();
                $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
                abort_unless(in_array($locked->status, ['pending', 'confirmed'], true)
                    && ($locked->status === 'pending' || $locked->starts_at?->isFuture()), 409, 'This appointment can no longer be changed.');
                $before = $locked->only(['preferred_date', 'preferred_time', 'preferred_church', 'group_baptism_session_id', 'starts_at', 'ends_at', 'reserved_until', 'confirmed_church', 'personnel_id']);
                $locked->fill($request->safe()->only(['preferred_date', 'preferred_time', 'preferred_church', 'group_baptism_session_id']));
                $locked->version++;
                $locked->save();
                $reserveAppointmentSlot->handle($locked);
                $locked->refresh();
                OfficeAudit::record('appointment.preference_changed', $locked, $before, $locked->only(array_keys($before)), 'Requested by booking contact');
                OfficeAudit::notify($locked, 'rescheduled', 'Client changed preferred schedule: '.$locked->reference_number);

            });
        } catch (ValidationException $exception) {
            return redirect()->to($access->issue($request, $appointment))
                ->withErrors($exception->errors())
                ->withInput($request->only(['preferred_date', 'preferred_time', 'preferred_church', 'group_baptism_session_id']));
        }

        return redirect()->to($access->issue($request, $appointment))->with(
            'appointment_message',
            $appointment->refresh()->status === 'confirmed'
                ? 'Your new date, time, and venue are confirmed and shown on the parish calendar.'
                : 'Your new date, time, and venue are reserved. Please submit the required physical documents within three days.',
        );
    }
}
