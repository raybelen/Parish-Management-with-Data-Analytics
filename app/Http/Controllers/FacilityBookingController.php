<?php

namespace App\Http\Controllers;

use App\Actions\NotifyFacilityBookingClient;
use App\Http\Requests\CancelFacilityBookingRequest;
use App\Http\Requests\FindFacilityBookingRequest;
use App\Http\Requests\RecoverFacilityBookingRequest;
use App\Http\Requests\StoreFacilityBookingRequest;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\OfficeNotification;
use App\Support\FacilityAvailability;
use App\Support\FacilityBookingAccess;
use App\Support\OfficeAudit;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FacilityBookingController extends Controller
{
    public function index(): Response
    {
        $facilities = Facility::query()->where('source', 'operational')->where('active', true)->orderBy('name')->paginate(12);

        return response()->view('client-side.facilities', ['facilities' => $facilities, 'pageTitle' => 'Facility booking'])->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreFacilityBookingRequest $request, Facility $facility, FacilityAvailability $availability, NotifyFacilityBookingClient $notifyClient): RedirectResponse
    {
        abort_unless($facility->source === 'operational' && $facility->active, 404);
        $data = $request->validated();
        $start = CarbonImmutable::parse($data['starts_at'], config('office.timezone'));
        $end = CarbonImmutable::parse($data['ends_at'], config('office.timezone'));
        $rateLimitKey = 'facility-submission:'.hash('sha256', mb_strtolower($data['organizer']).'|'.$data['contact_email']);
        /** @var FacilityBooking|false $booking */
        $booking = RateLimiter::attempt(
            $rateLimitKey,
            (int) config('facilities.submission_limit'),
            function () use ($facility, $data, $start, $end, $availability): FacilityBooking {
                return DB::transaction(function () use ($facility, $data, $start, $end, $availability): FacilityBooking {
                    $facility = Facility::query()->lockForUpdate()->findOrFail($facility->id);
                    $conflict = $availability->policyConflict($facility, $start, $end, (int) $data['attendees'], $data['activity'])
                        ?? $availability->conflict($facility, $start, $end);
                    if ($conflict) {
                        throw ValidationException::withMessages(['starts_at' => $conflict]);
                    }
                    $booking = $facility->bookings()->create([
                        ...$data, 'kind' => 'reservation', 'status' => 'pending', 'reference' => 'FAC-'.Str::upper(Str::random(16)),
                        'starts_at' => $start, 'ends_at' => $end,
                        'expires_at' => $start->subHours((int) config('facilities.approval_cutoff_hours')),
                    ]);
                    OfficeAudit::record('facility.reservation.requested', $booking, [], $booking->toArray());
                    OfficeNotification::create([
                        'facility_booking_id' => $booking->id,
                        'event' => 'facility.requested',
                        'message' => 'Facility request '.$booking->reference.' awaits approval.',
                        'deduplication_key' => 'facility.requested:'.$booking->id,
                    ]);

                    return $booking;
                });
            },
            (int) config('facilities.submission_decay_seconds'),
        );
        if ($booking === false) {
            $submissionLimit = (int) config('facilities.submission_limit');
            throw ValidationException::withMessages([
                'organizer' => "This organizer and email have reached the limit of {$submissionLimit} facility requests within 24 hours. Please try again later or contact the parish office.",
            ]);
        }
        $notifyClient->handle($booking->load('facility'), 'submitted');

        return to_route('facilities.index')->with([
            'status' => 'Your facility request is pending staff approval. The time is reserved only after approval.',
            'facility_reference' => $booking->reference,
        ]);
    }

    public function manage(): View
    {
        return view('client-side.facility-manage', ['pageTitle' => 'Manage a Facility Request']);
    }

    public function lookup(FindFacilityBookingRequest $request, FacilityBookingAccess $access): RedirectResponse
    {
        $data = $request->validated();
        $booking = FacilityBooking::query()->where('reference', $data['reference'])
            ->whereRaw('LOWER(contact_email) = ?', [$data['contact_email']])
            ->whereHas('facility', fn (Builder $query): Builder => $query->where('source', 'operational'))
            ->first();

        if ($booking === null) {
            return back()
                ->withErrors(['reference' => 'We could not find a facility request matching those details.'])
                ->withInput(['reference' => $data['reference']]);
        }

        return redirect()->to($access->issue($request, $booking));
    }

    public function emailAccess(Request $request, FacilityBooking $booking, FacilityBookingAccess $access): RedirectResponse
    {
        abort_unless($booking->facility?->source === 'operational', 404);

        return redirect()->to($access->issue($request, $booking));
    }

    public function show(Request $request, FacilityBooking $booking, FacilityBookingAccess $access): Response|RedirectResponse
    {
        abort_unless($booking->facility?->source === 'operational', 404);
        if (! $access->consume($request, $booking)) {
            return to_route('facilities.lookup.create')->with('facility_access_notice', 'Please enter the facility reference and contact email again.');
        }

        return response()->view('client-side.facility-request', [
            'booking' => $booking,
            'cancelUrl' => URL::temporarySignedRoute('facilities.bookings.destroy', now()->addMinutes(30), ['booking' => $booking->reference]),
            'pageTitle' => 'Facility request '.$booking->reference,
        ])->withHeaders([
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function recover(RecoverFacilityBookingRequest $request, FacilityBookingAccess $access): Response|RedirectResponse
    {
        $data = $request->validated();
        $bookings = FacilityBooking::query()->with('facility')
            ->whereRaw('LOWER(organizer) = ?', [mb_strtolower($data['organizer'])])
            ->whereRaw('LOWER(contact_email) = ?', [$data['contact_email']])
            ->whereHas('facility', fn (Builder $query): Builder => $query->where('source', 'operational'))
            ->latest('id')->limit(10)->get();

        if ($bookings->isEmpty()) {
            return back()
                ->withErrors([
                    'organizer' => 'We could not find a facility request matching those details.',
                ], 'recovery')
                ->withInput($request->safe()->only(['organizer', 'contact_email']));
        }

        return response()->view('client-side.facility-manage', [
            'pageTitle' => 'Recover a Facility Reference',
            'recoveredFacilityBookings' => $bookings->map(fn (FacilityBooking $booking): array => [
                'facility' => $booking->facility->name,
                'reference' => $booking->reference,
                'schedule' => $booking->starts_at->format('M j, Y g:i A').' – '.$booking->ends_at->format('g:i A'),
                'status' => match ($booking->status) {
                    'pending' => 'Pending approval',
                    'confirmed' => 'Approved',
                    'cancelled' => 'Canceled',
                    default => Str::headline($booking->status),
                },
                'url' => $access->issue($request, $booking),
            ]),
        ])->withHeaders([
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
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

        return to_route('facilities.lookup.create')->with('status', 'Facility booking cancelled.');
    }
}
