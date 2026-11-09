<?php

namespace App\Http\Controllers;

use App\Actions\RecordPayment;
use App\Actions\RequestPaymentAdjustment;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Appointment;
use App\Support\OfficeAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminPaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Appointment $appointment, RecordPayment $record, RequestPaymentAdjustment $adjustments): RedirectResponse
    {
        OfficeAccess::appointment($request->user(), $appointment);
        if ($request->validated('kind') === 'refund') {
            $adjustments->handle($appointment, 'refund', $request->validated());

            return back()->with('status', 'Refund request submitted. A different super administrator must approve it before the ledger changes.');
        }
        $record->handle($appointment, $request->validated());

        return back()->with('status', 'Payment ledger entry saved.');
    }

    public function update(Request $request, Appointment $appointment, RequestPaymentAdjustment $adjustments): RedirectResponse
    {
        Gate::authorize('payments.manage');
        OfficeAccess::appointment($request->user(), $appointment);
        $data = $request->validate(['idempotency_key' => ['required', 'uuid'], 'fee' => ['required', 'numeric', 'min:0', 'max:9999999', 'regex:/^\d+(\.\d{1,2})?$/'], 'reason' => ['required', 'string', 'max:1000'], 'version' => ['required', 'integer']]);
        $adjustments->handle($appointment, 'fee', $data);

        return back()->with('status', 'Fee adjustment requested. A different super administrator must approve it before the fee changes.');
    }
}
