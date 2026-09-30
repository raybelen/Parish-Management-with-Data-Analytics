<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelAppointmentRequest;
use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;

class AppointmentCancellationController extends Controller
{
    public function store(CancelAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $cancelled = Appointment::query()
            ->whereKey($appointment->getKey())
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        abort_if($cancelled !== 1, 409, 'This appointment request can no longer be cancelled.');

        return redirect()->to(URL::temporarySignedRoute(
            'appointments.show',
            now()->addMinutes(30),
            ['appointment' => $appointment],
        ))->with('appointment_message', 'Your appointment request has been cancelled.');
    }
}
