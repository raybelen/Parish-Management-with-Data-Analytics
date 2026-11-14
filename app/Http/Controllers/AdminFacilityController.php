<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveFacilityRequest;
use App\Models\Facility;
use App\Support\FacilityAvailability;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminFacilityController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('facilities.view');
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $facilities = Facility::query()->where('source', OfficeSettings::source())
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query->whereLike('name', '%'.$search.'%'))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('active', $status === 'active'))
            ->orderBy('name')->paginate(12)->withQueryString();

        return view('admin.facilities.index', compact('facilities', 'filters'));
    }

    public function create(): View
    {
        Gate::authorize('facilities.manage');

        return view('admin.facilities.form', ['facility' => new Facility(['active' => true, 'opening_hours' => [], 'permitted_activities' => []])]);
    }

    public function store(SaveFacilityRequest $request): RedirectResponse
    {
        $facility = DB::transaction(function () use ($request): Facility {
            $facility = Facility::create([...$request->facilityData(), 'source' => OfficeSettings::source()]);
            OfficeAudit::record('facility.created', $facility, [], $facility->toArray());

            return $facility;
        });

        return to_route('admin.facilities.show', $facility)->with('status', 'Facility created.');
    }

    public function show(Request $request, Facility $facility): View
    {
        Gate::authorize('facilities.view');
        abort_unless($facility->source === OfficeSettings::source(), 404);
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = CarbonImmutable::parse($data['date'] ?? now(config('office.timezone'))->toDateString(), config('office.timezone'))->startOfDay();
        $bookings = $facility->bookings()->where('starts_at', '<', $date->addDay())->where('ends_at', '>', $date)->orderBy('starts_at')->orderBy('id')->paginate(25)->withQueryString();
        $upcoming = $facility->bookings()->whereNull('cancelled_at')->where('ends_at', '>', now(config('office.timezone')))
            ->orderBy('starts_at')->orderBy('id')->paginate(10, ['*'], 'upcoming_page')->withQueryString();
        $hours = $facility->opening_hours[$date->dayOfWeekIso] ?? [];
        $freePeriods = [];
        if ($facility->active && ($hours['open'] ?? false) && $date->endOfDay()->isFuture()) {
            $cursor = $date->setTimeFromTimeString($hours['opens_at'])->max(CarbonImmutable::now(config('office.timezone'))->ceilMinute());
            $closing = $date->setTimeFromTimeString($hours['closes_at']);
            $occupied = $facility->bookings()->whereNull('cancelled_at')
                ->where(function (Builder $query): void {
                    $query->where('status', 'confirmed')
                        ->orWhere(fn (Builder $pending): Builder => $pending->where('status', 'pending')
                            ->where(fn (Builder $active): Builder => $active->whereNull('expires_at')->orWhere('expires_at', '>', now())));
                })
                ->where('starts_at', '<', $closing)->where('ends_at', '>', $cursor)->orderBy('starts_at')->get();
            foreach ($occupied as $booking) {
                if ($booking->starts_at->greaterThan($cursor)) {
                    $freePeriods[] = [$cursor, $booking->starts_at];
                }
                $cursor = $cursor->max($booking->ends_at);
            }
            if ($cursor->lessThan($closing)) {
                $freePeriods[] = [$cursor, $closing];
            }
        }

        return view('admin.facilities.show', compact('facility', 'date', 'bookings', 'upcoming', 'hours', 'freePeriods'));
    }

    public function edit(Facility $facility): View
    {
        Gate::authorize('facilities.manage');
        abort_unless($facility->source === OfficeSettings::source(), 404);

        return view('admin.facilities.form', compact('facility'));
    }

    public function update(SaveFacilityRequest $request, Facility $facility, FacilityAvailability $availability): RedirectResponse
    {
        abort_unless($facility->source === OfficeSettings::source(), 404);
        DB::transaction(function () use ($request, $facility, $availability): void {
            $facility = Facility::query()->lockForUpdate()->findOrFail($facility->id);
            $before = $facility->toArray();
            $facility->fill($request->facilityData());
            foreach ($facility->bookings()->whereNull('cancelled_at')->where('kind', 'reservation')
                ->where(function (Builder $query): void {
                    $query->where('status', 'confirmed')
                        ->orWhere(fn (Builder $pending): Builder => $pending->where('status', 'pending')
                            ->where(fn (Builder $active): Builder => $active->whereNull('expires_at')->orWhere('expires_at', '>', now())));
                })
                ->where('ends_at', '>', now(config('office.timezone')))->lazyById() as $booking) {
                $conflict = $availability->policyConflict($facility, $booking->starts_at, $booking->ends_at, $booking->attendees, $booking->activity);
                if ($conflict) {
                    throw ValidationException::withMessages(['facility' => 'These changes would invalidate reservation #'.$booking->id.'. Cancel it before changing this facility. '.$conflict]);
                }
            }
            $facility->save();
            OfficeAudit::record('facility.updated', $facility, $before, $facility->toArray());
        });

        return to_route('admin.facilities.show', $facility)->with('status', 'Facility updated. Existing reservation fees are unchanged.');
    }
}
