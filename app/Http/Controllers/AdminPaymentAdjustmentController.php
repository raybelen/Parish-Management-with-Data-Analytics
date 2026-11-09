<?php

namespace App\Http\Controllers;

use App\Actions\ReviewPaymentAdjustment;
use App\Models\PaymentAdjustment;
use App\Support\OfficeAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminPaymentAdjustmentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('payments.approve');
        $data = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])]]);
        $status = $data['status'] ?? 'pending';

        return view('admin.payment-adjustments', [
            'status' => $status,
            'adjustments' => PaymentAdjustment::query()
                ->whereIn('appointment_id', OfficeAccess::appointments($request->user())->select('appointments.id'))
                ->where('status', $status)->with(['appointment.payments', 'requestedBy', 'reviewedBy'])
                ->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function update(Request $request, PaymentAdjustment $adjustment, ReviewPaymentAdjustment $review): RedirectResponse
    {
        Gate::authorize('payments.approve');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $review->handle($adjustment, $data['decision'], $data['reason']);

        return back()->with('status', 'Adjustment request '.$data['decision'].'.');
    }
}
