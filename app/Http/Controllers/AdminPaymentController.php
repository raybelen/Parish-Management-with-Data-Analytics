<?php

namespace App\Http\Controllers;

use App\Actions\RecordPayment;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Appointment;
use App\Support\OfficeAccess;
use App\Support\OfficeAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminPaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Appointment $appointment, RecordPayment $record): RedirectResponse
    {
        OfficeAccess::appointment($request->user(), $appointment);
        $record->handle($appointment, $request->validated());

        return back()->with('status', 'Payment ledger entry saved.');
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('payments.manage');
        OfficeAccess::appointment($request->user(), $appointment);
        $data = $request->validate(['fee' => ['required', 'numeric', 'min:0', 'max:9999999', 'regex:/^\d+(\.\d{1,2})?$/'], 'reason' => ['required', 'string', 'max:1000'], 'version' => ['required', 'integer']]);
        DB::transaction(function () use ($appointment, $data): void {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless($locked->version === (int) $data['version'], 409);
            $before = $locked->fee_cents;
            $locked->fee_cents = RecordPayment::cents($data['fee']);
            $locked->version++;
            $locked->save();
            OfficeAudit::record('appointment.fee_changed', $locked, ['fee_cents' => $before], ['fee_cents' => $locked->fee_cents], $data['reason']);
        });

        return back()->with('status', 'Appointment fee updated.');
    }
}
