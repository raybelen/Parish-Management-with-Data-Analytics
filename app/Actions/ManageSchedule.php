<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\ParishSetting;
use App\Models\Personnel;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use App\Support\PriestRotation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageSchedule
{
    public function __construct(private PriestRotation $priestRotation) {}

    /** @param array<string, mixed> $data
     * @return list<Schedule>
     */
    public function create(array $data): array
    {
        $data['priest_required'] = (bool) ($data['priest_required'] ?? false);
        $data = $this->normalizeGroupSession($data);

        return DB::transaction(function () use ($data): array {
            ParishSetting::query()->where('key', 'office')->lockForUpdate()->firstOrFail();
            $start = CarbonImmutable::parse($data['starts_at'], config('office.timezone'));
            $end = CarbonImmutable::parse($data['ends_at'], config('office.timezone'));
            $until = $data['recurrence'] === 'none' ? $start : CarbonImmutable::parse($data['recurrence_until'], config('office.timezone'))->endOfDay();
            $occurrences = match ($data['recurrence']) {
                'daily' => (int) $start->startOfDay()->diffInDays($until->startOfDay()) + 1,
                'weekly' => (int) ($start->startOfDay()->diffInDays($until->startOfDay()) / 7) + 1,
                'monthly' => ($until->year - $start->year) * 12 + $until->month - $start->month + 1,
                'yearly' => $until->year - $start->year + 1,
                default => 1,
            };
            if ($occurrences > 367) {
                throw ValidationException::withMessages(['recurrence_until' => 'A series can contain at most 366 occurrences. Choose an earlier end date.']);
            }
            $series = $data['recurrence'] === 'none' ? null : (string) Str::uuid();
            $created = [];
            for ($index = 0; ; $index++) {
                $next = match ($data['recurrence']) {
                    'daily' => $start->addDays($index),
                    'weekly' => $start->addWeeks($index),
                    'monthly' => $start->addMonthsNoOverflow($index),
                    'yearly' => $start->addYearsNoOverflow($index),
                    default => $start,
                };
                if ($next > $until || ($index > 0 && $series === null)) {
                    break;
                }
                if ($index >= 366) {
                    throw ValidationException::withMessages(['recurrence_until' => 'A series can contain at most 366 occurrences. Choose an earlier end date.']);
                }
                $attributes = [...$data, 'source' => OfficeSettings::source(), 'series_id' => $series, 'starts_at' => $next, 'ends_at' => $next->addSeconds($start->diffInSeconds($end)), 'version' => 1];
                if ($attributes['priest_required']) {
                    $attributes['personnel_id'] = null;
                }
                $this->validateTimes($attributes);
                $schedule = Schedule::create($attributes);
                $this->priestRotation->assignSchedule($schedule);
                OfficeAudit::record('schedule.created', $schedule, [], $schedule->toArray());
                $created[] = $schedule;
            }

            return $created;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Schedule $schedule, array $data): void
    {
        DB::transaction(function () use ($schedule, $data): void {
            ParishSetting::query()->where('key', 'office')->lockForUpdate()->firstOrFail();
            $locked = Schedule::query()->lockForUpdate()->findOrFail($schedule->id);
            $this->checkVersion($locked, (int) $data['version']);
            $before = $locked->toArray();
            $data['priest_required'] = (bool) ($data['priest_required'] ?? $locked->priest_required);
            $data['starts_at'] = CarbonImmutable::parse($data['starts_at'], config('office.timezone'));
            $data['ends_at'] = CarbonImmutable::parse($data['ends_at'], config('office.timezone'));
            $data = $this->normalizeGroupSession($data);
            if ($this->protectEnrolledSession($locked, $data)) {
                $locked->fill([...$data, 'version' => $locked->version + 1])->save();
                OfficeAudit::record('schedule.updated', $locked, $before, $locked->toArray());

                return;
            }
            $this->validateTimes([...$locked->only(['location', 'personnel_id']), ...$data], $locked->id);
            $locked->fill([...$data, 'version' => $locked->version + 1])->save();
            $this->priestRotation->assignSchedule($locked);
            OfficeAudit::record($locked->status === 'cancelled' ? 'schedule.cancelled' : 'schedule.updated', $locked, $before, $locked->toArray());
        });
    }

    public function delete(Schedule $schedule, int $version): void
    {
        DB::transaction(function () use ($schedule, $version): void {
            ParishSetting::query()->where('key', 'office')->lockForUpdate()->firstOrFail();
            $locked = Schedule::query()->lockForUpdate()->findOrFail($schedule->id);
            $this->checkVersion($locked, $version);
            if (Appointment::query()->where('group_baptism_session_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['schedule' => 'A group session with appointment history cannot be deleted.']);
            }
            OfficeAudit::record('schedule.deleted', $locked, $locked->toArray());
            $locked->delete();
        });
    }

    public static function conflicts(CarbonImmutable $start, CarbonImmutable $end, ?string $location, ?int $personnelId, ?int $except = null): bool
    {
        return Schedule::query()->where('source', OfficeSettings::source())->where('status', 'scheduled')
            ->when($except, fn (Builder $query) => $query->whereKeyNot($except))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->where(function (Builder $query) use ($location, $personnelId): void {
                $query->whereRaw('1 = 0');
                if ($location) {
                    $query->orWhereRaw('LOWER(location) = ?', [mb_strtolower(trim($location))]);
                }
                if ($personnelId) {
                    $query->orWhere('personnel_id', $personnelId);
                }
            })->exists();
    }

    /** @param array<string, mixed> $data */
    private function validateTimes(array $data, ?int $except = null): void
    {
        $start = $data['starts_at'];
        $end = $data['ends_at'];
        if ($end > $start->addDays(31)) {
            throw ValidationException::withMessages(['ends_at' => 'Each schedule must be no longer than 31 days.']);
        }
        if ($data['status'] === 'completed' && $end > CarbonImmutable::now(config('office.timezone'))) {
            throw ValidationException::withMessages(['status' => 'A future schedule cannot be marked completed.']);
        }
        if (($data['priest_required'] ?? false) && ! $end->equalTo($start->addHour())) {
            throw ValidationException::withMessages(['ends_at' => 'Activities requiring a priest must last exactly one hour.']);
        }
        if (in_array($data['category'] ?? '', ['Group Baptism', 'Group Wedding', 'Group Confirmation'], true)) {
            if (! $end->equalTo($start->addHour())) {
                throw ValidationException::withMessages(['ends_at' => 'Group sessions must last exactly one hour.']);
            }
            if (! OfficeSettings::venueIsActive($data['location'] ?? '')) {
                throw ValidationException::withMessages(['location' => 'Choose an active church or chapel for the group session.']);
            }
            if ($data['status'] === 'scheduled') {
                $candidate = new Appointment([
                    'service_type' => mb_strtolower(substr($data['category'], 6)), 'source' => OfficeSettings::source(),
                    'preferred_date' => $start->toDateString(), 'preferred_time' => $start->format('H:i'), 'preferred_church' => $data['location'],
                ]);
                $message = app(ReserveAppointmentSlot::class)->conflictMessage($candidate, exceptSchedule: $except);
                if ($message !== null) {
                    throw ValidationException::withMessages(['starts_at' => $message]);
                }
            }
        }
        if ($data['status'] !== 'scheduled') {
            return;
        }
        $location = $data['location'] ?? null;
        $personnel = isset($data['personnel_id']) ? (int) $data['personnel_id'] : null;
        if ($personnel !== null) {
            $assigned = Personnel::query()->find($personnel);
            if ($assigned !== null && $this->priestRotation->isUnavailable($assigned, $start)) {
                throw ValidationException::withMessages(['personnel_id' => 'This priest is unavailable on the selected date.']);
            }
        }
        $resources = function (Builder $query, string $column) use ($location, $personnel): void {
            $query->whereRaw('1 = 0');
            if ($location) {
                $query->orWhereRaw('LOWER('.$column.') = ?', [mb_strtolower(trim($location))]);
            }
            if ($personnel) {
                $query->orWhere('personnel_id', $personnel);
            }
        };
        $appointment = Appointment::query()->where('source', OfficeSettings::source())->whereIn('status', ['pending', 'confirmed'])
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->where(fn (Builder $query) => $resources($query, 'confirmed_church'))->exists();
        $block = ScheduleBlock::query()->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->where(fn (Builder $query) => $resources($query, 'church'))->exists();
        if ($appointment || $block || self::conflicts($start, $end, $location, $personnel, $except)) {
            throw ValidationException::withMessages(['starts_at' => 'Schedule conflict on '.$start->format('M j, Y g:i A').': the location or assigned personnel is already reserved. Choose another time, location, or person.']);
        }
        if ($location) {
            for ($date = $start->startOfDay(); $date < $end; $date = $date->addDay()) {
                foreach (config('parish.regular_schedules', []) as $regular) {
                    if (mb_strtolower(trim($location)) !== mb_strtolower($regular['church'] ?? config('parish.churches.0'))) {
                        continue;
                    }
                    if (! in_array($date->dayOfWeekIso, $regular['days'], true)) {
                        continue;
                    }
                    foreach ($regular['times'] as $time) {
                        $mass = $date->setTimeFromTimeString($time['value']);
                        if ($start < $mass->addMinutes(($regular['duration_minutes'] ?? 60) + ($regular['buffer_minutes'] ?? 30)) && $end > $mass) {
                            throw ValidationException::withMessages(['starts_at' => 'This schedule overlaps a regular Mass or its preparation gap.']);
                        }
                    }
                }
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function protectEnrolledSession(Schedule $session, array $data): bool
    {
        if (! Appointment::query()->where('group_baptism_session_id', $session->id)->whereIn('status', ['pending', 'confirmed'])->exists()) {
            return false;
        }
        $changes = (clone $session)->fill($data)->getDirty();
        if (array_diff(array_keys($changes), ['group_booking_open', 'group_capacity', 'version']) === []) {
            return true;
        }
        throw ValidationException::withMessages(['schedule' => 'This group session has active family bookings. Cancel or move those requests before changing the session.']);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeGroupSession(array $data): array
    {
        if (in_array(mb_strtolower(trim($data['category'] ?? '')), ['group baptism', 'group wedding', 'group confirmation'], true)) {
            $data['category'] = ucwords(mb_strtolower(trim($data['category'])));
            $data['priest_required'] = true;
            $data['group_capacity'] = null;
            $data['group_booking_open'] = $data['status'] === 'scheduled' && (bool) ($data['group_booking_open'] ?? false);
        } else {
            $data['group_booking_open'] = false;
        }

        return $data;
    }

    private function checkVersion(Schedule $schedule, int $version): void
    {
        if ($schedule->version !== $version) {
            abort(409, 'This schedule changed since you opened it. Close and reopen its details before saving.');
        }
    }
}
