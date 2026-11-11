<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Support\AppointmentAccess;
use App\Support\FinancialDocumentSeal;
use App\Support\OfficeAccess;
use App\Support\OfficeSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class AppointmentBillingController extends Controller
{
    public function show(Request $request, Appointment $appointment, AppointmentAccess $access): Response|RedirectResponse
    {
        if (! $access->consume($request, $appointment)) {
            return redirect()->route('appointments.lookup.create')->with('appointment_access_notice', 'Please verify the appointment reference and contact information again.');
        }

        return $this->statement($appointment, route('appointments.lookup.create'));
    }

    public function admin(Request $request, Appointment $appointment): Response
    {
        Gate::authorize('finance.view');
        OfficeAccess::appointment($request->user(), $appointment);

        return $this->statement($appointment);
    }

    private function statement(Appointment $appointment, ?string $manageUrl = null): Response
    {
        $appointment->load(['payments.originalPayment', 'invoices' => fn ($query) => $query->orderByDesc('revision')]);
        foreach ($appointment->invoices->concat($appointment->payments) as $document) {
            abort_if($document->integrity_seal !== null && ! FinancialDocumentSeal::valid($document), 409, 'A financial document failed its integrity check. Contact the parish administrator.');
        }

        return response()->view('client-side.appointments.billing', [
            'appointment' => $appointment,
            'manageUrl' => $manageUrl,
            'parishName' => OfficeSettings::all()['parish_name'],
            'serviceLabel' => OfficeSettings::services()[$appointment->service_type]['label'] ?? Str::headline($appointment->service_type),
        ])->withHeaders(['Cache-Control' => 'no-store, private, max-age=0', 'Pragma' => 'no-cache', 'Expires' => '0', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
