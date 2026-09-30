<?php

namespace App\Http\Controllers;

use App\Http\Requests\FindAppointmentRequest;
use App\Models\Appointment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AppointmentLookupController extends Controller
{
    public function create(): View
    {
        return view('client-side.appointments.manage', [
            'pageTitle' => 'Manage an Appointment',
        ]);
    }

    public function store(FindAppointmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $contactInformation = Str::lower(trim($validated['contact_information']));
        $contactNumber = preg_replace('/\D+/', '', $contactInformation) ?? '';

        $appointment = Appointment::query()
            ->where('reference_number', $validated['reference_number'])
            ->where(function (Builder $query) use ($contactInformation, $contactNumber): void {
                $query->where('client_email', $contactInformation);

                if ($contactNumber !== '') {
                    $query->orWhere('client_contact_key', $contactNumber);
                }
            })
            ->first();

        if ($appointment === null) {
            return back()
                ->withErrors(['reference_number' => 'We could not find an appointment matching those details.'])
                ->withInput(['reference_number' => $validated['reference_number']]);
        }

        return redirect()->to(URL::temporarySignedRoute(
            'appointments.show',
            now()->addMinutes(30),
            ['appointment' => $appointment],
        ));
    }
}
