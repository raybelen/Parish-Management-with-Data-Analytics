<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelAppointmentRequest;
use App\Models\Appointment;
use App\Support\AppointmentAccess;
use App\Support\OfficeAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AppointmentCancellationController extends Controller
{
    public function store(CancelAppointmentRequest $request, Appointment $appointment, AppointmentAccess $access): RedirectResponse
    {
        DB::transaction(function () use ($appointment): void {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless($locked->status === 'pending', 409, 'This appointment request can no longer be cancelled.');
            $locked->status = 'cancelled';
            $locked->outcome = 'Cancelled by booking contact';
            $locked->version++;
            $locked->save();
            OfficeAudit::record('appointment.cancelled', $locked, ['status' => 'pending'], ['status' => 'cancelled'], $locked->outcome);
            OfficeAudit::notify($locked, 'cancelled', 'Client cancelled: '.$locked->reference_number);
        });

        return redirect()->to($access->issue($request, $appointment))
            ->with('appointment_message', 'Your appointment request has been cancelled.');
    }
}
