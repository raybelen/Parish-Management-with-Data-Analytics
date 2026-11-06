<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\PriestUnavailability;
use App\Models\Schedule;
use App\Support\OfficeAudit;
use App\Support\PriestRotation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminPriestUnavailabilityController extends Controller
{
    public function store(Request $request, PriestRotation $rotation): RedirectResponse
    {
        Gate::authorize('schedules.manage');
        $data = $request->validate([
            'personnel_id' => ['required', 'integer', Rule::exists('personnel', 'id')->where('active', true)->whereNotNull('rotation_position')],
            'unavailable_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'unavailable_through' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:unavailable_from'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $unavailableFrom = CarbonImmutable::createFromFormat('!Y-m-d', $data['unavailable_from']);
        $unavailableThrough = isset($data['unavailable_through'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $data['unavailable_through'])
            : $unavailableFrom;

        DB::transaction(function () use ($data, $rotation, $unavailableFrom, $unavailableThrough): void {
            for ($date = $unavailableFrom; $date->lessThanOrEqualTo($unavailableThrough); $date = $date->addDay()) {
                $unavailability = PriestUnavailability::query()->updateOrCreate(
                    ['personnel_id' => $data['personnel_id'], 'unavailable_on' => $date->toDateString()],
                    ['reason' => $data['reason'] ?? null],
                );
                OfficeAudit::record('priest.unavailable', $unavailability, [], $unavailability->toArray());
            }

            Appointment::query()->where('personnel_id', $data['personnel_id'])->whereIn('status', ['pending', 'confirmed'])
                ->whereBetween('starts_at', [$unavailableFrom->startOfDay(), $unavailableThrough->endOfDay()])->orderBy('starts_at')->get()
                ->each(fn (Appointment $appointment) => $rotation->assignAppointment($appointment));
            Schedule::query()->where('personnel_id', $data['personnel_id'])->where('priest_required', true)->where('status', 'scheduled')
                ->whereBetween('starts_at', [$unavailableFrom->startOfDay(), $unavailableThrough->endOfDay()])->orderBy('starts_at')->get()
                ->each(fn (Schedule $schedule) => $rotation->assignSchedule($schedule));
        });

        $dateCount = (int) $unavailableFrom->diffInDays($unavailableThrough) + 1;
        $dateLabel = $dateCount === 1 ? 'date' : 'dates';

        return back()->with('status', "Priest unavailability saved for {$dateCount} {$dateLabel} and affected activities reassigned where possible.");
    }

    public function destroy(PriestUnavailability $unavailability): RedirectResponse
    {
        Gate::authorize('schedules.manage');
        OfficeAudit::record('priest.available', $unavailability, $unavailability->toArray(), []);
        $unavailability->delete();

        return back()->with('status', 'Priest unavailability removed.');
    }
}
