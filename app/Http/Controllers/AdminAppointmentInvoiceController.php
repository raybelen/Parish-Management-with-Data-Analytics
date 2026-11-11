<?php

namespace App\Http\Controllers;

use App\Actions\IssueAppointmentInvoice;
use App\Models\Appointment;
use App\Support\OfficeAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminAppointmentInvoiceController extends Controller
{
    public function store(Request $request, Appointment $appointment, IssueAppointmentInvoice $issue): RedirectResponse
    {
        Gate::authorize('payments.manage');
        Gate::authorize('finance.view');
        OfficeAccess::appointment($request->user(), $appointment);
        $data = $request->validate(['fee_cents' => ['required', 'integer', 'min:0']]);
        $invoice = $issue->handle($appointment, (int) $data['fee_cents']);

        return redirect()->to(route('admin.appointments.billing', $appointment).'#invoice-'.$invoice->id)
            ->with('status', 'Invoice ready to print.');
    }
}
