<?php

namespace App\Http\Controllers;

use App\Actions\NotifyFacilityBookingClient;
use App\Http\Requests\CancelFacilityBookingRequest;
use App\Http\Requests\RecoverFacilityBookingRequest;
use App\Http\Requests\StoreFacilityBookingRequest;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Support\FacilityAvailability;
use App\Support\OfficeAudit;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FacilityBookingController extends Controller
{
    public function index(Request $request): Response
    {
        $facilities = Facility::query()->where('source', 'operational')->where('active', true)->orderBy('name')->paginate(12);
        $bookings = FacilityBooking::query()->with('facility')->whereIn('id', $request->session()->get('facility_bookings', []))->latest()->get();

        return response()->view('client-side.facilities', ['facilities' => $facilities, 'bookings' => $bookings, 'pageTitle' => 'Facility booking'])->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreFacilityBookingRequest $request, Facility $facility, FacilityAvailability $availability, NotifyFacilityBookingClient $notifyClient): RedirectResponse
    {
        abort_unless($facility->source === 'operational' && $facility->active, 404);
        $data = $request->validated();
        $start = CarbonImmutable::parse($data['starts_at'], config('office.timezone'));
        $end = CarbonImmutable::parse($data['ends_at'], config('office.timezone'));
        $booking = DB::transaction(function () use ($facility, $data, $start, $end, $availability): FacilityBooking {
            $facility = Facility::query()->lockForUpdate()->findOrFail($facility->id);
            $conflict = $availability->policyConflict($facility, $start, $end, (int) $data['attendees'], $data['activity'])
                ?? $availability->conflict($facility, $start, $end);
            if ($conflict) {
                throw ValidationException::withMessages(['starts_at' => $conflict]);
            }
            $booking = $facility->bookings()->create([
                ...$data, 'kind' => 'reservation', 'status' => 'unverified', 'reference' => 'FAC-'.Str::upper(Str::random(16)),
                'starts_at' => $start, 'ends_at' => $end,
                'expires_at' => now()->addMinutes(config('facilities.verification_minutes')),
            ]);
            OfficeAudit::record('facility.reservation.requested', $booking, [], $booking->toArray());

            return $booking;
        });
        $request->session()->put('facility_bookings', array_slice([...$request->session()->get('facility_bookings', []), $booking->id], -20));
        $notifyClient->handle($booking->load('facility'), 'verification');

        return to_route('facilities.index')->with('status', 'Check your email to verify request '.$booking->reference.'. The time is not held until verification.');
    }

    public function verify(Request $request, FacilityBooking $booking, FacilityAvailability $availability): RedirectResponse
    {
        abort_unless($booking->facility?->source === 'operational', 404);
        $result = DB::transaction(function () use ($booking, $availability): string {
            $facility = Facility::query()->lockForUpdate()->findOrFail($booking->facility_id);
            $booking = FacilityBooking::query()->lockForUpdate()->findOrFail($booking->id);
            if ($booking->status !== 'unverified' || $booking->cancelled_at !== null) {
                return $booking->status;
            }
            if ($booking->expires_at?->isPast()) {
                $booking->update(['status' => 'expired', 'cancelled_at' => now(), 'cancellation_reason' => 'Email verification link expired.']);
                OfficeAudit::record('facility.reservation.expired', $booking, ['status' => 'unverified'], $booking->toArray());

                return 'expired';
            }
            $conflict = $availability->policyConflict($facility, $booking->starts_at, $booking->ends_at, $booking->attendees, $booking->activity)
                ?? $availability->conflict($facility, $booking->starts_at, $booking->ends_at);
            if ($conflict !== null) {
                throw ValidationException::withMessages(['request' => $conflict]);
            }
            $before = $booking->toArray();
            $booking->update(['status' => 'pending', 'expires_at' => now()->addHours(config('facilities.pending_hours'))]);
            OfficeAudit::record('facility.reservation.verified', $booking, $before, $booking->toArray());

            return 'pending';
        });
        $request->session()->put('facility_bookings', array_slice([...$request->session()->get('facility_bookings', []), $booking->id], -20));

        return redirect($this->accessUrl($booking))->with('status', $result === 'pending' ? 'Email verified. The time is held while parish staff review your request.' : 'This request is '.$result.'.');
    }

    public function show(FacilityBooking $booking): View
    {
        abort_unless($booking->facility?->source === 'operational', 404);

        return view('client-side.facility-request', [
            'booking' => $booking,
            'cancelUrl' => URL::temporarySignedRoute('facilities.bookings.destroy', now()->addMinutes(30), ['booking' => $booking->reference]),
            'pageTitle' => 'Facility request '.$booking->reference,
        ]);
    }

    public function recover(RecoverFacilityBookingRequest $request, NotifyFacilityBookingClient $notifyClient): RedirectResponse
    {
        $data = $request->validated();
        $booking = FacilityBooking::query()->with('facility')->where('reference', $data['reference'])
            ->whereRaw('LOWER(contact_email) = ?', [$data['contact_email']])->whereHas('facility', fn ($query) => $query->where('source', 'operational'))->first();
        if ($booking !== null) {
            $notifyClient->handle($booking, $booking->status === 'unverified' ? 'verification' : 'access');
        }

        return to_route('facilities.index')->with('status', 'If those details match a request, a secure access link has been emailed.');
    }

    public function destroy(CancelFacilityBookingRequest $request, FacilityBooking $booking, NotifyFacilityBookingClient $notifyClient): RedirectResponse
    {
        abort_unless($booking->facility?->source === 'operational', 404);
        $cancelled = DB::transaction(function () use ($request, $booking): ?FacilityBooking {
            Facility::query()->lockForUpdate()->findOrFail($booking->facility_id);
            $booking = FacilityBooking::query()->lockForUpdate()->findOrFail($booking->id);
            if ($booking->cancelled_at !== null) {
                return null;
            }
            if ($booking->ends_at->isPast()) {
                throw ValidationException::withMessages(['reason' => 'Past facility bookings cannot be cancelled.']);
            }
            $before = $booking->toArray();
            $booking->update(['status' => 'cancelled', 'expires_at' => null, 'cancelled_at' => now(), 'cancellation_reason' => $request->validated('reason')]);
            OfficeAudit::record('facility.reservation.cancelled', $booking, $before, $booking->toArray(), $request->validated('reason'));

            return $booking->load('facility');
        });
        if ($cancelled !== null) {
            $notifyClient->handle($cancelled, 'cancelled');
        }

        return to_route('facilities.index')->with('status', 'Facility booking cancelled.');
    }

    private function accessUrl(FacilityBooking $booking): string
    {
        return URL::temporarySignedRoute('facilities.bookings.show', now()->addDays(config('facilities.access_days')), ['booking' => $booking->reference]);
    }
}
