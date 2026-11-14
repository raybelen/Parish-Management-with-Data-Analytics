<?php

namespace App\Http\Controllers;

use App\Actions\NotifyFacilityBookingClient;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Support\FacilityAvailability;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminFacilityBookingController extends Controller
{
    public function store(Request $request, Facility $facility, FacilityAvailability $availability): RedirectResponse
    {
        Gate::authorize('facilities.manage');
        abort_unless($facility->source === OfficeSettings::source(), 404);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['reservation', 'closure'])],
            'title' => ['required', 'string', 'max:150'],
            'organizer' => ['exclude_unless:kind,reservation', 'required', 'string', 'max:150'],
            'activity' => ['exclude_unless:kind,reservation', 'required', 'string', 'max:100'],
            'attendees' => ['exclude_unless:kind,reservation', 'required', 'integer', 'min:1', 'max:100000'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
        ]);
        $start = CarbonImmutable::parse($data['starts_at'], config('office.timezone'));
        $end = CarbonImmutable::parse($data['ends_at'], config('office.timezone'));
        DB::transaction(function () use ($request, $facility, $availability, $data, $start, $end): void {
            $facility = Facility::query()->lockForUpdate()->findOrFail($facility->id);
            $conflict = $data['kind'] === 'reservation'
                ? $availability->policyConflict($facility, $start, $end, (int) $data['attendees'], $data['activity'])
                : null;
            $conflict ??= $availability->conflict($facility, $start, $end);
            if ($conflict) {
                throw ValidationException::withMessages(['starts_at' => $conflict]);
            }
            $booking = $facility->bookings()->create([
                ...$data, 'starts_at' => $start, 'ends_at' => $end, 'recorded_by' => $request->user()->id,
                'status' => 'confirmed',
            ]);
            OfficeAudit::record('facility.'.$data['kind'].'.created', $booking, [], $booking->toArray());
        });

        return to_route('admin.facilities.show', ['facility' => $facility, 'date' => $start->toDateString()])->with('status', $data['kind'] === 'reservation' ? 'Facility reservation recorded.' : 'Facility closure recorded.');
    }

    public function update(Request $request, Facility $facility, FacilityBooking $booking, FacilityAvailability $availability, NotifyFacilityBookingClient $notifyClient): RedirectResponse
    {
        Gate::authorize('facilities.manage');
        abort_unless($facility->source === OfficeSettings::source() && $booking->facility_id === $facility->id, 404);
        $approved = DB::transaction(function () use ($facility, $booking, $availability): ?FacilityBooking {
            $facility = Facility::query()->lockForUpdate()->findOrFail($facility->id);
            $booking->refresh();
            if ($booking->cancelled_at || $booking->starts_at->isPast()) {
                throw ValidationException::withMessages(['booking' => 'Only upcoming, uncanceled requests can be approved.']);
            }
            if ($booking->expires_at?->isPast()) {
                throw ValidationException::withMessages(['booking' => 'This request has expired and can no longer be approved.']);
            }
            if ($booking->status !== 'pending') {
                return null;
            }
            $conflict = $availability->policyConflict($facility, $booking->starts_at, $booking->ends_at, $booking->attendees, $booking->activity);
            if ($conflict) {
                throw ValidationException::withMessages(['booking' => $conflict]);
            }
            $before = $booking->toArray();
            $booking->update(['status' => 'confirmed', 'expires_at' => null]);
            OfficeAudit::record('facility.reservation.approved', $booking, $before, $booking->toArray());

            return $booking->load('facility');
        });
        if ($approved !== null) {
            $notifyClient->handle($approved, 'approved');
        }

        return back()->with('status', 'Facility reservation approved.');
    }

    public function destroy(Request $request, Facility $facility, FacilityBooking $booking, NotifyFacilityBookingClient $notifyClient): RedirectResponse
    {
        Gate::authorize('facilities.manage');
        abort_unless($facility->source === OfficeSettings::source() && $booking->facility_id === $facility->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $cancelled = DB::transaction(function () use ($facility, $booking, $data): ?FacilityBooking {
            Facility::query()->lockForUpdate()->findOrFail($facility->id);
            $booking->refresh();
            if ($booking->cancelled_at) {
                return null;
            }
            if ($booking->ends_at->isPast()) {
                throw ValidationException::withMessages(['reason' => 'Completed reservations and past closures cannot be canceled.']);
            }
            $before = $booking->toArray();
            $booking->update(['status' => 'cancelled', 'expires_at' => null, 'cancelled_at' => now(), 'cancellation_reason' => $data['reason']]);
            OfficeAudit::record('facility.'.$booking->kind.'.cancelled', $booking, $before, $booking->toArray(), $data['reason']);

            return $booking->load('facility');
        });
        if ($cancelled !== null && $cancelled->kind === 'reservation') {
            $notifyClient->handle($cancelled, 'cancelled');
        }

        return back()->with('status', 'Facility booking canceled. The history has been kept.');
    }
}
