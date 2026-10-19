<?php

namespace App\Http\Controllers;

use App\Actions\CreateAppointment;
use App\Http\Requests\StoreAppointmentRequest;
use App\Mail\AppointmentRequestReceived;
use App\Models\Appointment;
use App\Support\AppointmentAccess;
use App\Support\OfficeSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

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
            'services' => OfficeSettings::services(),
            'churches' => OfficeSettings::activeVenues(),
        ]);
    }

    public function store(StoreAppointmentRequest $request, CreateAppointment $createAppointment, AppointmentAccess $access): RedirectResponse
    {
        $appointment = $createAppointment->handle($request->validated());

        if ($appointment->client_email !== null) {
            Mail::to($appointment->client_email)->queue(new AppointmentRequestReceived($appointment));
        }

        return redirect()->to($access->issue($request, $appointment));
    }

    public function show(Request $request, Appointment $appointment, AppointmentAccess $access): Response|RedirectResponse
    {
        if (! $access->consume($request, $appointment)) {
            return redirect()->route('appointments.lookup.create')->with(
                'appointment_access_notice',
                'Please verify the appointment reference and contact information again.',
            );
        }

        $canManageRequest = $appointment->status === 'pending';

        return response()->view('client-side.appointments.show', [
            'pageTitle' => 'Appointment Confirmation',
            'appointment' => $appointment,
            'service' => OfficeSettings::services()[$appointment->service_type] ?? ['label' => Str::headline($appointment->service_type)],
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
        ])->withHeaders([
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
