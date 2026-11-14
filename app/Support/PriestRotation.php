<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Personnel;
use App\Models\PriestUnavailability;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PriestRotation
{
    /** @var Collection<int, Personnel>|null */
    private ?Collection $priestCache = null;

    /** @var array<string, bool> */
    private array $unavailabilityCache = [];

    /** @var array<string, Collection<int, array<string, mixed>>> */
    private array $activityCache = [];

    /** @return Collection<int, Personnel> */
    public function priests(): Collection
    {
        return $this->priestCache ??= Personnel::query()->where('active', true)->whereNotNull('rotation_position')
            ->orderBy('rotation_position')->get();
    }

    public function configured(): bool
    {
        return $this->priests()->pluck('rotation_position')->all() === [1, 2, 3]
            && $this->anchorDate() !== null && $this->anchorPosition() !== null;
    }

    public function warmPeriod(CarbonImmutable $from, CarbonImmutable $through): void
    {
        $from = $from->startOfDay();
        $through = $through->endOfDay();
        $priests = $this->priests();
        $priestIds = $priests->pluck('id');

        for ($date = $from; $date <= $through; $date = $date->addDay()) {
            $this->activityCache[$date->toDateString()] = collect();
            foreach ($priests as $priest) {
                $this->unavailabilityCache[$priest->id.':'.$date->toDateString()] = false;
            }
        }

        PriestUnavailability::query()->whereIn('personnel_id', $priestIds)
            ->whereBetween('unavailable_on', [$from->toDateString(), $through->toDateString()])
            ->get(['personnel_id', 'unavailable_on'])
            ->each(function (PriestUnavailability $unavailability): void {
                $this->unavailabilityCache[$unavailability->personnel_id.':'.$unavailability->unavailable_on->toDateString()] = true;
            });

        Appointment::query()->whereIn('status', ['pending', 'confirmed'])->whereNotNull('personnel_id')->whereNull('group_baptism_session_id')
            ->where('starts_at', '<=', $through)->where('ends_at', '>', $from)
            ->get(['id', 'personnel_id', 'starts_at', 'ends_at'])
            ->each(fn (Appointment $appointment) => $this->cacheActivity([
                'type' => 'appointment', 'id' => $appointment->id, 'personnel_id' => $appointment->personnel_id,
                'starts_at' => $appointment->starts_at, 'ends_at' => $appointment->ends_at, 'workload' => true,
            ], $from, $through));
        Schedule::query()->where('status', 'scheduled')->whereNotNull('personnel_id')
            ->where('starts_at', '<=', $through)->where('ends_at', '>', $from)
            ->get(['id', 'personnel_id', 'priest_required', 'starts_at', 'ends_at'])
            ->each(fn (Schedule $schedule) => $this->cacheActivity([
                'type' => 'schedule', 'id' => $schedule->id, 'personnel_id' => $schedule->personnel_id,
                'starts_at' => $schedule->starts_at, 'ends_at' => $schedule->ends_at, 'workload' => $schedule->priest_required,
            ], $from, $through));
        ScheduleBlock::query()->whereNotNull('personnel_id')
            ->where('starts_at', '<=', $through)->where('ends_at', '>', $from)
            ->get(['id', 'personnel_id', 'starts_at', 'ends_at'])
            ->each(fn (ScheduleBlock $block) => $this->cacheActivity([
                'type' => 'block', 'id' => $block->id, 'personnel_id' => $block->personnel_id,
                'starts_at' => $block->starts_at, 'ends_at' => $block->ends_at, 'workload' => false,
            ], $from, $through));
    }

    public function regularPriest(CarbonImmutable $date): ?Personnel
    {
        $position = $this->regularPosition($date);

        return $position === null ? null : $this->priests()->firstWhere('rotation_position', $position);
    }

    public function regularPosition(CarbonImmutable $date): ?int
    {
        $anchorDate = $this->anchorDate();
        $anchorPosition = $this->anchorPosition();
        if ($anchorDate === null || $anchorPosition === null) {
            return null;
        }

        $days = (int) $anchorDate->startOfDay()->diffInDays($date->startOfDay(), false);

        return (($anchorPosition - 1 + $days) % 3 + 3) % 3 + 1;
    }

    public function assignAppointment(Appointment $appointment): ?Personnel
    {
        if ($appointment->requiresOfficeApproval()) {
            return $appointment->personnel;
        }

        if ($appointment->starts_at === null || $appointment->ends_at === null) {
            return null;
        }

        if ($appointment->group_baptism_session_id) {
            $session = Schedule::query()->with('personnel')->findOrFail($appointment->group_baptism_session_id);
            $appointment->forceFill(['personnel_id' => $session->personnel_id])->save();
            $this->forgetDate($appointment->starts_at);

            return $session->personnel;
        }

        $priest = $this->unexpectedPriest($appointment->starts_at, $appointment->ends_at, $appointment->id, null, $appointment->personnel);
        $appointment->forceFill(['personnel_id' => $priest?->id])->save();
        $this->forgetDate($appointment->starts_at);

        return $priest;
    }

    public function assignSchedule(Schedule $schedule): ?Personnel
    {
        if (! $schedule->priest_required || $schedule->status !== 'scheduled') {
            return $schedule->personnel;
        }

        $priest = $this->unexpectedPriest($schedule->starts_at, $schedule->ends_at, null, $schedule->id, $schedule->personnel);
        $schedule->forceFill(['personnel_id' => $priest?->id])->save();
        if (in_array($schedule->category, ['Group Baptism', 'Group Wedding', 'Group Confirmation'], true)) {
            Appointment::query()->where('group_baptism_session_id', $schedule->id)->whereIn('status', ['pending', 'confirmed'])
                ->increment('version', 1, ['personnel_id' => $priest?->id]);
        }
        $this->forgetDate($schedule->starts_at);

        return $priest;
    }

    public function unexpectedPriest(CarbonImmutable $start, CarbonImmutable $end, ?int $exceptAppointment = null, ?int $exceptSchedule = null, ?Personnel $preferred = null): ?Personnel
    {
        if (! $this->configured()) {
            return null;
        }

        $massAssignments = collect($this->regularMassAssignments($start->startOfDay(), $exceptAppointment, $exceptSchedule));
        $busyAtMass = $massAssignments->filter(fn (array $mass): bool => $mass['starts_at'] < $end && $mass['ends_at'] > $start)
            ->pluck('personnel_id')->filter()->all();
        $regularPosition = $this->regularPosition($start);
        $priority = $this->unexpectedPriority($start, $regularPosition, $exceptAppointment, $exceptSchedule);

        $eligible = $this->priests()
            ->reject(fn (Personnel $priest): bool => $this->isUnavailable($priest, $start)
                || in_array($priest->id, $busyAtMass, true)
                || $this->hasStoredConflict($priest, $start, $end, $exceptAppointment, $exceptSchedule));
        if ($preferred !== null && $eligible->contains('id', $preferred->id)) {
            return $eligible->firstWhere('id', $preferred->id);
        }

        return $eligible
            ->sortBy(fn (Personnel $priest): array => [
                $this->dailyWorkload($priest, $start, $exceptAppointment, $exceptSchedule, $massAssignments),
                array_search($priest->rotation_position, $priority, true),
            ])->first();
    }

    /** @return list<array<string, mixed>> */
    public function regularMassAssignments(CarbonImmutable $date, ?int $exceptAppointment = null, ?int $exceptSchedule = null): array
    {
        $masses = collect(config('parish.regular_schedules', []))->flatMap(function (array $schedule, int $index) use ($date): array {
            if (! in_array($date->dayOfWeekIso, $schedule['days'], true)) {
                return [];
            }

            return collect($schedule['times'])->map(function (array $time) use ($date, $schedule, $index): array {
                $start = $date->setTimeFromTimeString($time['value']);

                return [
                    'key' => $index.'-'.$start->format('YmdHi'),
                    'title' => $schedule['service'],
                    'location' => $schedule['church'] ?? config('parish.churches.0'),
                    'starts_at' => $start,
                    'ends_at' => $start->addHour(),
                ];
            })->all();
        })->sortBy('starts_at')->values();

        $regularPosition = $this->regularPosition($date);
        $assigned = collect();

        return $masses->map(function (array $mass, int $rotationOffset) use ($regularPosition, $assigned, $exceptAppointment, $exceptSchedule): array {
            $scheduledPosition = $regularPosition === null
                ? null
                : (($regularPosition - 1 + $rotationOffset) % 3) + 1;
            $regular = $scheduledPosition === null
                ? null
                : $this->priests()->firstWhere('rotation_position', $scheduledPosition);
            $eligible = $this->priests()->reject(fn (Personnel $priest): bool => $this->isUnavailable($priest, $mass['starts_at'])
                || $this->hasStoredConflict($priest, $mass['starts_at'], $mass['ends_at'], $exceptAppointment, $exceptSchedule)
                || $assigned->contains(fn (array $other): bool => $other['personnel_id'] === $priest->id
                    && $other['starts_at'] < $mass['ends_at'] && $other['ends_at'] > $mass['starts_at']));

            $priest = $regular !== null && $eligible->contains('id', $regular->id)
                ? $regular
                : $eligible->sortBy(fn (Personnel $candidate): array => [
                    $this->dailyWorkload($candidate, $mass['starts_at'], $exceptAppointment, $exceptSchedule, $assigned),
                    $candidate->rotation_position,
                ])->first();

            $assignment = [...$mass,
                'regular_personnel_id' => $regular?->id,
                'regular_personnel' => $regular?->name,
                'personnel_id' => $priest?->id,
                'personnel' => $priest?->name,
                'assignment_status' => $priest === null ? 'needs_reassignment' : ($priest->is($regular) ? 'regular' : 'substitute'),
            ];
            $assigned->push($assignment);

            return $assignment;
        })->all();
    }

    public function isUnavailable(Personnel $priest, CarbonImmutable $date): bool
    {
        $key = $priest->id.':'.$date->toDateString();

        return $this->unavailabilityCache[$key] ??= PriestUnavailability::query()->whereBelongsTo($priest)
            ->whereDate('unavailable_on', $date->toDateString())->exists();
    }

    private function hasStoredConflict(Personnel $priest, CarbonImmutable $start, CarbonImmutable $end, ?int $exceptAppointment = null, ?int $exceptSchedule = null): bool
    {
        return $this->storedActivities($start)->contains(fn (array $activity): bool => $activity['personnel_id'] === $priest->id
            && ! ($activity['type'] === 'appointment' && $activity['id'] === $exceptAppointment)
            && ! ($activity['type'] === 'schedule' && $activity['id'] === $exceptSchedule)
            && $activity['starts_at'] < $end && $activity['ends_at'] > $start);
    }

    /** @param Collection<int, array<string, mixed>> $massAssignments */
    private function dailyWorkload(Personnel $priest, CarbonImmutable $date, ?int $exceptAppointment, ?int $exceptSchedule, Collection $massAssignments): int
    {
        $stored = $this->storedActivities($date)->filter(fn (array $activity): bool => $activity['workload']
            && $activity['personnel_id'] === $priest->id
            && ! ($activity['type'] === 'appointment' && $activity['id'] === $exceptAppointment)
            && ! ($activity['type'] === 'schedule' && $activity['id'] === $exceptSchedule))->count();

        return $stored + $massAssignments->where('personnel_id', $priest->id)->count();
    }

    private function anchorDate(): ?CarbonImmutable
    {
        $value = OfficeSettings::all()['priest_rotation_anchor_date'] ?? null;

        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value, config('office.timezone')) : null;
    }

    /** @return list<int> */
    private function unexpectedPriority(CarbonImmutable $date, ?int $regularPosition, ?int $exceptAppointment, ?int $exceptSchedule): array
    {
        $positions = collect([1, 2, 3])->reject(fn (int $position): bool => $position === $regularPosition)->values();
        if ($regularPosition !== null) {
            $positions->push($regularPosition);
        }
        $unexpected = $this->storedActivities($date)->filter(fn (array $activity): bool => $activity['workload']
            && $activity['personnel_id'] !== null
            && ! ($activity['type'] === 'appointment' && $activity['id'] === $exceptAppointment)
            && ! ($activity['type'] === 'schedule' && $activity['id'] === $exceptSchedule))->count();

        for ($index = 0; $index < $unexpected % 3; $index++) {
            $positions->push($positions->shift());
        }

        return $positions->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function storedActivities(CarbonImmutable $date): Collection
    {
        $key = $date->toDateString();
        if (isset($this->activityCache[$key])) {
            return $this->activityCache[$key];
        }
        $from = $date->startOfDay();
        $to = $date->endOfDay();
        $appointments = Appointment::query()->whereIn('status', ['pending', 'confirmed'])->whereNotNull('personnel_id')->whereNull('group_baptism_session_id')
            ->where('starts_at', '<=', $to)->where('ends_at', '>', $from)->get(['id', 'personnel_id', 'starts_at', 'ends_at'])
            ->map(fn (Appointment $appointment): array => ['type' => 'appointment', 'id' => $appointment->id, 'personnel_id' => $appointment->personnel_id, 'starts_at' => $appointment->starts_at, 'ends_at' => $appointment->ends_at, 'workload' => true]);
        $schedules = Schedule::query()->where('status', 'scheduled')->whereNotNull('personnel_id')
            ->where('starts_at', '<=', $to)->where('ends_at', '>', $from)->get(['id', 'personnel_id', 'priest_required', 'starts_at', 'ends_at'])
            ->map(fn (Schedule $schedule): array => ['type' => 'schedule', 'id' => $schedule->id, 'personnel_id' => $schedule->personnel_id, 'starts_at' => $schedule->starts_at, 'ends_at' => $schedule->ends_at, 'workload' => $schedule->priest_required]);
        $blocks = ScheduleBlock::query()->whereNotNull('personnel_id')->where('starts_at', '<=', $to)->where('ends_at', '>', $from)
            ->get(['id', 'personnel_id', 'starts_at', 'ends_at'])
            ->map(fn (ScheduleBlock $block): array => ['type' => 'block', 'id' => $block->id, 'personnel_id' => $block->personnel_id, 'starts_at' => $block->starts_at, 'ends_at' => $block->ends_at, 'workload' => false]);

        return $this->activityCache[$key] = $appointments->concat($schedules)->concat($blocks)->values();
    }

    private function forgetDate(CarbonImmutable $date): void
    {
        unset($this->activityCache[$date->toDateString()]);
    }

    /** @param array<string, mixed> $activity */
    private function cacheActivity(array $activity, CarbonImmutable $from, CarbonImmutable $through): void
    {
        for ($date = $activity['starts_at']->startOfDay(); $date <= $activity['ends_at']->startOfDay(); $date = $date->addDay()) {
            if ($date < $from || $date > $through || $activity['starts_at'] > $date->endOfDay() || $activity['ends_at'] <= $date) {
                continue;
            }
            $this->activityCache[$date->toDateString()] ??= collect();
            $this->activityCache[$date->toDateString()]->push($activity);
        }
    }

    private function anchorPosition(): ?int
    {
        $position = OfficeSettings::all()['priest_rotation_anchor_position'] ?? null;

        return in_array((int) $position, [1, 2, 3], true) ? (int) $position : null;
    }
}
