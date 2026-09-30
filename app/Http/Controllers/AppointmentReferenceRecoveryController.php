<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecoverAppointmentReferenceRequest;
use App\Models\Appointment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AppointmentReferenceRecoveryController extends Controller
{
    public function store(RecoverAppointmentReferenceRequest $request): View|RedirectResponse
    {
        $validated = $request->validated();
        $fullName = Str::lower($validated['recovery_full_name']);
        $contactInformation = $validated['recovery_contact_information'];
        $contactNumber = preg_replace('/\D+/', '', $contactInformation) ?? '';

        $appointments = Appointment::query()
            ->select([
                'id',
                'reference_number',
                'status',
                'client_full_name',
                'service_type',
                'preferred_date',
                'preferred_church',
                'created_at',
            ])
            ->whereRaw('LOWER(client_full_name) = ?', [$fullName])
            ->where(function (Builder $query) use ($contactInformation, $contactNumber): void {
                $query->where('client_email', $contactInformation);

                if ($contactNumber !== '') {
                    $query->orWhere('client_contact_key', $contactNumber);
                }
            })
            ->latest('preferred_date')
            ->limit(10)
            ->get();

        if ($appointments->isEmpty()) {
            return back()
                ->withErrors([
                    'recovery_full_name' => 'We could not find an appointment matching those details.',
                ], 'recovery')
                ->withInput($request->safe()->only([
                    'recovery_full_name',
                    'recovery_contact_information',
                ]));
        }

        return view('client-side.appointments.manage', [
            'pageTitle' => 'Recover an Appointment Reference',
            'recoveredAppointments' => $appointments->map(fn (Appointment $appointment): array => [
                'reference_number' => $appointment->reference_number,
                'status' => Str::headline($appointment->status),
                'service' => config("appointments.services.{$appointment->service_type}.label", Str::headline($appointment->service_type)),
                'preferred_date' => $appointment->preferred_date->format('F j, Y'),
                'preferred_church' => $appointment->preferred_church,
                'url' => URL::temporarySignedRoute(
                    'appointments.show',
                    now()->addMinutes(30),
                    ['appointment' => $appointment],
                ),
            ]),
        ]);
    }
}
