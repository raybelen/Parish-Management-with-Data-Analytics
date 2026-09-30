<?php

namespace App\Http\Controllers;

use App\Actions\CreateAppointment;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;

class AppointmentController extends Controller
{
    public function index(): View
    {
        return view('client-side.appointments.index', [
            'pageTitle' => 'Appointments',
        ]);
    }

    public function create(): View
    {
        return view('client-side.appointments.create', [
            'pageTitle' => 'Request an Appointment',
            'services' => config('appointments.services'),
            'churches' => config('parish.churches'),
        ]);
    }

    public function store(StoreAppointmentRequest $request, CreateAppointment $createAppointment): RedirectResponse
    {
        $appointment = $createAppointment->handle($request->validated());

        return redirect()->to(URL::temporarySignedRoute(
            'appointments.show',
            now()->addMinutes(30),
            ['appointment' => $appointment],
        ));
    }

    public function show(Appointment $appointment): View
    {
        $canManageRequest = $appointment->status === 'pending';

        return view('client-side.appointments.show', [
            'pageTitle' => 'Appointment Confirmation',
            'appointment' => $appointment,
            'service' => config('appointments.services.'.$appointment->service_type),
            'canManageRequest' => $canManageRequest,
            'scheduleUpdateUrl' => $canManageRequest ? URL::temporarySignedRoute(
                'appointments.schedule.update',
                now()->addMinutes(30),
                ['appointment' => $appointment],
            ) : null,
            'cancellationUrl' => $canManageRequest ? URL::temporarySignedRoute(
                'appointments.cancellation.store',
                now()->addMinutes(30),
                ['appointment' => $appointment],
            ) : null,
        ]);
    }
}
