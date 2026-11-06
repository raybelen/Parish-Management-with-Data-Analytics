<?php

namespace App\Http\Controllers;

use App\Actions\CreateAppointment;
use App\Actions\StartXenditPayment;
use App\Http\Requests\StoreAppointmentRequest;
use App\Mail\AppointmentReserved;
use App\Models\Appointment;
use App\Services\XenditPaymentGateway;
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
            'xenditSandboxAvailable' => XenditPaymentGateway::isConfigured(),
        ]);
    }

    public function store(
        StoreAppointmentRequest $request,
        CreateAppointment $createAppointment,
        AppointmentAccess $access,
        StartXenditPayment $startXenditPayment,
    ): RedirectResponse {
        $appointment = $createAppointment->handle($request->validated());

        if ($appointment->wantsEmailNotifications()) {
            Mail::to($appointment->client_email)->queue(
                (new AppointmentReserved($appointment))->onConnection('deferred'),
            );
        }

        $appointmentUrl = $access->issue($request, $appointment);
        if ($appointment->canStartOnlinePayment()) {
            try {
                $transaction = $startXenditPayment->handle($appointment);

                return redirect()->away((string) $transaction->checkout_url);
            } catch (\Throwable $exception) {
                report($exception);

                return redirect()->to($appointmentUrl)->with(
                    'payment_error',
                    'Xendit Sandbox checkout could not be started. Your appointment is still reserved, and you may pay at the parish office.',
                );
            }
        }

        return redirect()->to($appointmentUrl);
    }

    public function show(Request $request, Appointment $appointment, AppointmentAccess $access): Response|RedirectResponse
    {
        if (! $access->consume($request, $appointment)) {
            return redirect()->route('appointments.lookup.create')->with(
                'appointment_access_notice',
                'Please verify the appointment reference and contact information again.',
            );
        }

        $canManageRequest = in_array($appointment->status, ['pending', 'confirmed'], true)
            && ($appointment->status === 'pending' || $appointment->starts_at?->isFuture());
        $appointment->load(['payments', 'paymentTransactions' => fn ($query) => $query->latest('id')]);
        $paymentUrl = $appointment->canStartOnlinePayment() && XenditPaymentGateway::isConfigured()
            ? URL::temporarySignedRoute(
                'appointments.payments.xendit',
                now()->addMinutes(30),
                ['appointment' => $appointment],
            )
            : null;

        return response()->view('client-side.appointments.show', [
            'pageTitle' => 'Appointment Reservation',
            'appointment' => $appointment,
            'service' => OfficeSettings::services()[$appointment->service_type] ?? ['label' => Str::headline($appointment->service_type)],
            'canManageRequest' => $canManageRequest,
            'paymentUrl' => $paymentUrl,
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
