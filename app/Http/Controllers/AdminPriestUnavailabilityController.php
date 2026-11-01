<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\PriestUnavailability;
use App\Models\Schedule;
use App\Support\OfficeAudit;
use App\Support\PriestRotation;
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
            'unavailable_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($data, $rotation): void {
            $unavailability = PriestUnavailability::query()->updateOrCreate(
                ['personnel_id' => $data['personnel_id'], 'unavailable_on' => $data['unavailable_on']],
                ['reason' => $data['reason'] ?? null],
            );
            Appointment::query()->where('personnel_id', $data['personnel_id'])->whereIn('status', ['pending', 'confirmed'])
                ->whereDate('starts_at', $data['unavailable_on'])->orderBy('starts_at')->get()
                ->each(fn (Appointment $appointment) => $rotation->assignAppointment($appointment));
            Schedule::query()->where('personnel_id', $data['personnel_id'])->where('priest_required', true)->where('status', 'scheduled')
                ->whereDate('starts_at', $data['unavailable_on'])->orderBy('starts_at')->get()
                ->each(fn (Schedule $schedule) => $rotation->assignSchedule($schedule));
            OfficeAudit::record('priest.unavailable', $unavailability, [], $unavailability->toArray());
        });

        return back()->with('status', 'Priest unavailability saved and affected activities reassigned where possible.');
    }

    public function destroy(PriestUnavailability $unavailability): RedirectResponse
    {
        Gate::authorize('schedules.manage');
        OfficeAudit::record('priest.available', $unavailability, $unavailability->toArray(), []);
        $unavailability->delete();

        return back()->with('status', 'Priest unavailability removed.');
    }
}
