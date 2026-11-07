<?php

namespace App\Http\Controllers;

use App\Actions\NotifyAppointmentClient;
use App\Http\Requests\CancelAppointmentRequest;
use App\Models\Appointment;
use App\Support\AppointmentAccess;
use App\Support\OfficeAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AppointmentCancellationController extends Controller
{
    public function store(CancelAppointmentRequest $request, Appointment $appointment, AppointmentAccess $access, NotifyAppointmentClient $notifyClient): RedirectResponse
    {
        DB::transaction(function () use ($appointment, $notifyClient): void {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless(in_array($locked->status, ['pending', 'confirmed'], true)
                && ($locked->status === 'pending' || $locked->starts_at?->isFuture()), 409, 'This appointment can no longer be cancelled.');
            $previousStatus = $locked->status;
            $locked->status = 'cancelled';
            $locked->outcome = 'Cancelled by booking contact';
            $locked->version++;
            $locked->save();
            OfficeAudit::record('appointment.cancelled', $locked, ['status' => $previousStatus], ['status' => 'cancelled'], $locked->outcome);
            OfficeAudit::notify($locked, 'cancelled', 'Client cancelled: '.$locked->reference_number);
            $notifyClient->handle($locked, 'cancelled');
        });

        return redirect()->to($access->issue($request, $appointment))
            ->with('appointment_message', 'Your appointment has been cancelled and removed from the parish calendar.');
    }
}
