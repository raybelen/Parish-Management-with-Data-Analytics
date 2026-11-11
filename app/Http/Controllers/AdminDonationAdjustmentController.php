<?php

namespace App\Http\Controllers;

use App\Actions\ManageDonation;
use App\Models\Donation;
use App\Models\DonationAdjustment;
use App\Support\OfficeSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminDonationAdjustmentController extends Controller
{
    public function store(Request $request, Donation $donation, ManageDonation $manage): RedirectResponse
    {
        Gate::authorize('donations.view');
        Gate::authorize('donations.manage');
        abort_unless($donation->source === OfficeSettings::source(), 404);
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'], 'version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $manage->requestAdjustment($donation, $request->user(), 'void', $data);

        return redirect()->route('admin.donations.show', $donation)->with('status', 'Void request submitted for independent Super Admin review.');
    }

    public function update(Request $request, DonationAdjustment $adjustment, ManageDonation $manage): RedirectResponse
    {
        Gate::authorize('donations.view');
        Gate::authorize('payments.approve');
        abort_unless($adjustment->donation->source === OfficeSettings::source(), 404);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'review_reason' => ['required', 'string', 'max:1000'],
        ]);
        $manage->review($adjustment, $request->user(), $data['decision'], $data['review_reason']);

        return redirect()->route('admin.donations.show', $adjustment->donation_id)->with('status', 'Donation request '.$data['decision'].'.');
    }
}
