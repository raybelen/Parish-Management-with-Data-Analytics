<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\ParishSetting;
use App\Models\Personnel;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Models\ServiceType;
use App\Support\OfficeSettings;
use App\Support\PriestRotation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReserveAppointmentSlot
{
    /** @var array<string, bool> */
    private array $activeServiceCache = [];

    /** @var array<string, Collection<int, Appointment>> */
    private array $appointmentCache = [];

    /** @var array<string, Collection<int, Schedule>> */
    private array $scheduleCache = [];

    /** @var array<string, Collection<int, ScheduleBlock>> */
    private array $scheduleBlockCache = [];

    public function __construct(private PriestRotation $priestRotation) {}

    public function handle(Appointment $appointment): bool
    {
        return DB::transaction(function () use ($appointment): bool {
            ParishSetting::query()->where('key', 'office')->lockForUpdate()->firstOrFail();
            OfficeSettings::forgetMemoizedValues();
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $conflict = $this->conflictMessage($locked);
            if ($conflict !== null) {
                throw ValidationException::withMessages(['preferred_time' => $conflict]);
            }
            $start = $this->start($locked);
            $documentsComplete = $locked->status === 'confirmed' || $locked->physicalDocumentsComplete();
            $locked->forceFill([
                'starts_at' => $start, 'ends_at' => $start->addHour(), 'reserved_until' => $start->addHour(),
                'confirmed_church' => $locked->preferred_church, 'buffer_minutes' => 0,
                'status' => $documentsComplete ? 'confirmed' : 'pending',
                'documents_due_at' => $documentsComplete ? null : now(config('office.timezone'))->addDays(3),
            ])->save();
            $this->priestRotation->warmPeriod($start->startOfDay(), $start->endOfDay());
            $this->priestRotation->assignAppointment($locked);

            return true;
        });
    }

    public function start(Appointment $appointment): CarbonImmutable
    {
        return CarbonImmutable::parse($appointment->preferred_date->toDateString().' '.$appointment->preferred_time, config('office.timezone'));
    }

    public function conflictMessage(Appointment $appointment, ?int $personnelId = null, ?int $exceptSchedule = null, bool $memoize = false): ?string
    {
        $start = $this->start($appointment);
        $end = $start->addHour();
        $venue = $appointment->preferred_church;
        $type = $appointment->service_type;
        $source = $appointment->source ?? OfficeSettings::source();
        $sessionId = $appointment->group_baptism_session_id;
        $settings = OfficeSettings::all();

        if (! $start->isFuture()) {
            return 'This time has passed. Please choose a future date and time.';
        }
        if (! OfficeSettings::venueIsActive($venue)) {
            return 'This venue is unavailable. Please choose another venue.';
        }
        if (! in_array($start->dayOfWeekIso, $settings['operating_days'], true)
            || $start->format('H:i') < $settings['opens_at'] || $end->format('H:i') > $settings['closes_at']
            || ! (($start->format('H:i') >= '08:00' && $end->format('H:i') <= '11:00')
                || ($start->format('H:i') >= '13:00' && $end->format('H:i') <= '16:30'))
            || ! $start->isSameDay($end)) {
            return 'Choose an open day and a one-hour slot within 8:00-11:00 AM or 1:00-4:30 PM. The latest afternoon start is 3:30 PM.';
        }
        if ($type === 'wedding' && ! in_array($start->format('H:i'), ['08:00', '08:30', '09:00', '09:30', '10:00', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30'], true)) {
            return 'Weddings start every 30 minutes from 8:00-10:00 AM or 1:00-3:30 PM. Please choose one of these times.';
        }
        $serviceIsActive = $memoize
            ? ($this->activeServiceCache[$type] ??= ServiceType::query()->where('slug', $type)->where('active', true)->exists())
            : ServiceType::query()->where('slug', $type)->where('active', true)->exists();
        if (! $serviceIsActive) {
            return 'This service is unavailable. Please contact the parish office.';
        }
        if ($personnelId !== null) {
            $personnel = Personnel::query()->find($personnelId);
            if ($personnel !== null && $this->priestRotation->isUnavailable($personnel, $start)) {
                return 'This priest is unavailable on the selected date. Choose another priest.';
            }
        }

        $session = $sessionId ? Schedule::query()->find($sessionId) : null;
        if ($sessionId && ($type !== 'baptism' || ! $start->isSunday() || ! $session
            || $session->category !== 'Group Baptism' || $session->status !== 'scheduled' || $session->source !== $source
            || $session->location !== $venue || ! $session->starts_at->equalTo($start) || ! $session->ends_at->equalTo($end))) {
            return 'Please select an available Sunday group baptism session and use its date, time, and venue.';
        }
        if ($session && $session->group_capacity !== null) {
            $families = Appointment::query()->where('source', $source)->where('group_baptism_session_id', $sessionId)
                ->whereIn('status', ['pending', 'confirmed'])->when($appointment->exists, fn ($query) => $query->whereKeyNot($appointment->id))->count();
            if ($families >= $session->group_capacity) {
                return 'This group baptism session is full. Please choose another session or request a private baptism.';
            }
        }

        $others = $this->appointmentsForDate($appointment, $source, $start, $memoize);
        foreach ($others as $other) {
            $otherStart = $other->starts_at ?? $this->start($other);
            $otherEnd = $other->ends_at ?? $otherStart->addHour();
            if ($message = $this->periodConflict($type, $start, $other->service_type, $otherStart)) {
                return $message;
            }
            $sameVenue = ($other->confirmed_church ?? $other->preferred_church) === $venue;
            if (($sameVenue || ($personnelId && $other->personnel_id === $personnelId)) && $start < $otherEnd && $end > $otherStart) {
                return 'This time conflicts with an existing booking. Please choose another available time or venue.';
            }
            if ($sameVenue && $type === $other->service_type && in_array($type, ['wedding', 'funeral'], true)
                && $start < $otherEnd->addHour() && $end->addHour() > $otherStart) {
                return 'Consecutive '.$type.' services at the same venue need a one-hour gap. Please choose another available time or venue.';
            }
        }

        $schedules = $this->schedulesForDate($source, $start, $exceptSchedule, $memoize);
        foreach ($schedules as $schedule) {
            if ($schedule->category === 'Group Baptism' && $schedule->starts_at->isSameDay($start)
                && ($message = $this->periodConflict($type, $start, 'baptism', $schedule->starts_at))) {
                return $message;
            }
            if ($session && $schedule->id === $session->id) {
                continue;
            }
            if ((mb_strtolower(trim($schedule->location ?? '')) === mb_strtolower($venue)
                || ($personnelId && $schedule->personnel_id === $personnelId)) && $start < $schedule->ends_at && $end > $schedule->starts_at) {
                return 'This time conflicts with a parish activity. Please choose another available time or venue.';
            }
        }
        $blocked = $this->scheduleBlocksForDate($start, $memoize)->contains(fn (ScheduleBlock $block): bool => $block->starts_at < $end
            && $block->ends_at > $start
            && ($block->church === $venue || ($personnelId !== null && $block->personnel_id === $personnelId)));
        if ($blocked) {
            return 'This time is blocked. Please choose another available time or venue.';
        }
        foreach (config('parish.regular_schedules', []) as $mass) {
            if (($mass['church'] ?? config('parish.churches.0')) !== $venue || ! in_array($start->dayOfWeekIso, $mass['days'], true)) {
                continue;
            }
            foreach ($mass['times'] as $time) {
                $massStart = $start->setTimeFromTimeString($time['value']);
                $massEnd = $massStart->addMinutes(($mass['duration_minutes'] ?? 60) + ($mass['buffer_minutes'] ?? 30));
                if ($end <= $massStart && $end->addMinutes(30) > $massStart) {
                    return 'Your appointment must end at least 30 minutes before Mass. Please choose an earlier time or another venue.';
                }
                if ($start < $massEnd && $end > $massStart) {
                    return 'This time overlaps Mass or its reserved time afterward. Please choose another available time or venue.';
                }
            }
        }

        return null;
    }

    /** @return Collection<int, Appointment> */
    private function appointmentsForDate(Appointment $appointment, string $source, CarbonImmutable $start, bool $memoize): Collection
    {
        $load = fn (): Collection => Appointment::query()->where('source', $source)->whereIn('status', ['pending', 'confirmed', 'completed'])
            ->when($appointment->exists, fn ($query) => $query->whereKeyNot($appointment->id))
            ->where(function ($query) use ($start): void {
                $query->whereBetween('starts_at', [$start->startOfDay(), $start->endOfDay()])
                    ->orWhere(fn ($legacy) => $legacy->whereNull('starts_at')->where('status', 'confirmed')->whereDate('preferred_date', $start->toDateString()));
            })->get();

        if (! $memoize) {
            return $load();
        }

        $key = implode(':', [$source, $start->toDateString(), $appointment->exists ? $appointment->id : 'new']);

        return $this->appointmentCache[$key] ??= $load();
    }

    /** @return Collection<int, Schedule> */
    private function schedulesForDate(string $source, CarbonImmutable $start, ?int $exceptSchedule, bool $memoize): Collection
    {
        $load = fn (): Collection => Schedule::query()->where('source', $source)->where('status', 'scheduled')
            ->when($exceptSchedule, fn ($query) => $query->whereKeyNot($exceptSchedule))
            ->where('starts_at', '<', $start->addDay()->startOfDay())->where('ends_at', '>', $start->startOfDay())->get();

        if (! $memoize) {
            return $load();
        }

        $key = implode(':', [$source, $start->toDateString(), $exceptSchedule ?? 'none']);

        return $this->scheduleCache[$key] ??= $load();
    }

    /** @return Collection<int, ScheduleBlock> */
    private function scheduleBlocksForDate(CarbonImmutable $start, bool $memoize): Collection
    {
        $load = fn (): Collection => ScheduleBlock::query()
            ->where('starts_at', '<', $start->addDay()->startOfDay())
            ->where('ends_at', '>', $start->startOfDay())
            ->get();

        if (! $memoize) {
            return $load();
        }

        return $this->scheduleBlockCache[$start->toDateString()] ??= $load();
    }

    private function periodConflict(string $type, CarbonImmutable $start, string $otherType, CarbonImmutable $otherStart): ?string
    {
        $morning = $start->hour < 12;
        $otherMorning = $otherStart->hour < 12;
        if ((($type === 'wedding' && $otherType === 'funeral') || ($type === 'funeral' && $otherType === 'wedding')) && $morning === $otherMorning) {
            return 'Weddings and funerals need different morning or afternoon periods, even at different venues. Please choose the opposite period or another date.';
        }
        if (($type === 'funeral' && $morning && $otherType === 'baptism' && $otherMorning)
            || ($type === 'baptism' && $morning && $otherType === 'funeral' && $otherMorning)) {
            return 'Baptisms and funerals cannot both be in the morning, even at different venues. Please choose an afternoon time or another date.';
        }

        return null;
    }
}
