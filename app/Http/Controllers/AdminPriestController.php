<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Personnel;
use App\Models\PriestUnavailability;
use App\Models\Schedule;
use App\Support\OfficeSettings;
use App\Support\PriestRotation;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class AdminPriestController extends Controller
{
    public function __invoke(PriestRotation $rotation): View
    {
        Gate::authorize('schedules.manage');
        $from = CarbonImmutable::today(config('office.timezone'));
        $through = $from->addDays(30)->endOfDay();
        $rotation->warmPeriod($from, $through);

        return view('admin.priests.index', [
            'personnel' => Personnel::query()->where('active', true)->orderBy('name')->get(),
            'rotationPriests' => $rotation->priests()->keyBy('rotation_position'),
            'anchorDate' => OfficeSettings::all()['priest_rotation_anchor_date'] ?? null,
            'nextAppointment' => Appointment::query()->whereIn('status', ['pending', 'confirmed'])->where('starts_at', '>=', now())
                ->with('personnel')->orderBy('starts_at')->orderBy('id')->first(),
            'unavailabilities' => PriestUnavailability::query()->with('personnel')->whereDate('unavailable_on', '>=', $from)
                ->orderBy('unavailable_on')->orderBy('personnel_id')->get(),
            'scheduleRows' => $this->scheduleRows($rotation, $from, $through),
            'from' => $from,
            'through' => $through,
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function scheduleRows(PriestRotation $rotation, CarbonImmutable $from, CarbonImmutable $through): Collection
    {
        $appointments = Appointment::query()->whereIn('status', ['pending', 'confirmed'])
            ->whereBetween('starts_at', [$from, $through])->with('personnel')->get()->map(fn (Appointment $appointment): array => [
                'key' => 'appointment-'.$appointment->id, 'starts_at' => $appointment->starts_at, 'ends_at' => $appointment->ends_at,
                'activity_type' => ucfirst($appointment->service_type), 'title' => $appointment->reference_number,
                'personnel_id' => $appointment->personnel_id, 'personnel' => $appointment->personnel?->name,
                'assignment_status' => $appointment->personnel_id === null ? 'needs_reassignment' : 'assigned',
            ]);
        $schedules = Schedule::query()->where('priest_required', true)->where('status', 'scheduled')
            ->whereBetween('starts_at', [$from, $through])->with('personnel')->get()->map(fn (Schedule $schedule): array => [
                'key' => 'schedule-'.$schedule->id, 'starts_at' => $schedule->starts_at, 'ends_at' => $schedule->ends_at,
                'activity_type' => $schedule->category, 'title' => $schedule->title,
                'personnel_id' => $schedule->personnel_id, 'personnel' => $schedule->personnel?->name,
                'assignment_status' => $schedule->personnel_id === null ? 'needs_reassignment' : 'assigned',
            ]);
        $masses = collect();
        for ($date = $from; $date <= $through; $date = $date->addDay()) {
            $masses->push(...collect($rotation->regularMassAssignments($date))->map(fn (array $mass): array => [
                'key' => 'mass-'.$mass['key'], 'starts_at' => $mass['starts_at'], 'ends_at' => $mass['ends_at'],
                'activity_type' => 'Regular Mass', 'title' => $mass['title'],
                'personnel_id' => $mass['personnel_id'], 'personnel' => $mass['personnel'],
                'assignment_status' => $mass['assignment_status'], 'regular_personnel' => $mass['regular_personnel'],
            ])->all());
        }

        $rows = $appointments->concat($schedules)->concat($masses);
        $priests = Personnel::query()->whereKey($rows->pluck('personnel_id')->filter()->unique())->get()->keyBy('id');
        $rows->transform(function (array $row) use ($rows, $rotation, $priests): array {
            $priest = $row['personnel_id'] === null ? null : $priests->get($row['personnel_id']);
            if ($priest !== null && $rotation->isUnavailable($priest, $row['starts_at'])) {
                $row['assignment_status'] = 'unavailable';
            }
            if ($row['personnel_id'] !== null && $rows->contains(fn (array $other): bool => $other['key'] !== $row['key']
                && $other['personnel_id'] === $row['personnel_id'] && $other['starts_at'] < $row['ends_at'] && $other['ends_at'] > $row['starts_at'])) {
                $row['assignment_status'] = 'conflict';
            }

            return $row;
        });

        return $rows->sortBy([['starts_at', 'asc'], ['key', 'asc']])->values();
    }
}
