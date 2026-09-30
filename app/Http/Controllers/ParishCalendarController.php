<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

class ParishCalendarController extends Controller
{
    public function __invoke(): View
    {
        $firstMonth = CarbonImmutable::today()->startOfMonth();
        $lastMonth = $firstMonth->addMonth();
        $scheduledEvents = $this->scheduledEvents(
            $firstMonth,
            $lastMonth->endOfMonth(),
        );

        return view('client-side.calendar', [
            'pageTitle' => 'Calendar',
            'calendarRange' => $this->calendarRange($firstMonth, $lastMonth),
            'calendarMonths' => [
                $this->calendarMonth($firstMonth, $scheduledEvents),
                $this->calendarMonth($lastMonth, $scheduledEvents),
            ],
        ]);
    }

    /**
     * @return array<string, list<array{kind: string, name: string, time: string, time_value: string}>>
     */
    private function scheduledEvents(CarbonImmutable $startsAt, CarbonImmutable $endsAt): array
    {
        $appointments = Appointment::query()
            ->select(['service_type', 'preferred_date', 'preferred_time'])
            ->where('status', 'confirmed')
            ->whereBetween('preferred_date', [$startsAt->toDateString(), $endsAt->toDateString()])
            ->orderBy('preferred_date')
            ->orderBy('preferred_time')
            ->get();

        $events = [];

        foreach ($appointments as $appointment) {
            $events[$appointment->preferred_date->toDateString()][] = [
                'kind' => 'scheduled',
                'name' => config(
                    "appointments.services.{$appointment->service_type}.label",
                    Str::headline($appointment->service_type),
                ),
                'time' => $this->formatTime($appointment->preferred_time),
                'time_value' => mb_substr($appointment->preferred_time, 0, 5),
            ];
        }

        return $events;
    }

    /**
     * @param  array<string, list<array{kind: string, name: string, time: string, time_value: string}>>  $scheduledEvents
     * @return array{key: string, label: string, weeks: list<list<array{date: string, day: int, is_today: bool, events: list<array{kind: string, name: string, time: string, time_value: string}>}|null>>}
     */
    private function calendarMonth(CarbonImmutable $month, array $scheduledEvents): array
    {
        $days = array_fill(0, $month->dayOfWeek, null);
        $date = $month->startOfMonth();

        while ($date->format('Y-m') === $month->format('Y-m')) {
            $dateKey = $date->toDateString();
            $events = $this->regularEventsFor($date);
            array_push($events, ...($scheduledEvents[$dateKey] ?? []));
            usort($events, fn (array $first, array $second): int => [$first['time_value'], $first['name']] <=> [$second['time_value'], $second['name']]);

            $days[] = [
                'date' => $dateKey,
                'day' => $date->day,
                'is_today' => $date->isToday(),
                'events' => $events,
            ];
            $date = $date->addDay();
        }

        while (count($days) % 7 !== 0) {
            $days[] = null;
        }

        return [
            'key' => $month->format('Y-m'),
            'label' => $month->format('F Y'),
            'weeks' => array_chunk($days, 7),
        ];
    }

    /**
     * @return list<array{kind: string, name: string, time: string, time_value: string}>
     */
    private function regularEventsFor(CarbonImmutable $date): array
    {
        $events = [];

        foreach (config('parish.regular_schedules', []) as $schedule) {
            if (! in_array($date->dayOfWeekIso, $schedule['days'], true)) {
                continue;
            }

            foreach ($schedule['times'] as $time) {
                $events[] = [
                    'kind' => 'regular',
                    'name' => $schedule['service'],
                    'time' => $time['label'],
                    'time_value' => $time['value'],
                ];
            }
        }

        return $events;
    }

    private function calendarRange(CarbonImmutable $firstMonth, CarbonImmutable $lastMonth): string
    {
        if ($firstMonth->year === $lastMonth->year) {
            return $firstMonth->format('F').'–'.$lastMonth->format('F Y');
        }

        return $firstMonth->format('F Y').'–'.$lastMonth->format('F Y');
    }

    private function formatTime(string $time): string
    {
        $date = DateTimeImmutable::createFromFormat('!H:i:s', $time)
            ?: DateTimeImmutable::createFromFormat('!H:i', $time);

        return $date instanceof DateTimeImmutable ? $date->format('g:i A') : $time;
    }
}
