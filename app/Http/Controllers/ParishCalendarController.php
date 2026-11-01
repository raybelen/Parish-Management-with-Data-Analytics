<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Support\OfficeSettings;
use App\Support\PriestRotation;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

class ParishCalendarController extends Controller
{
    public function __invoke(PriestRotation $priestRotation): View
    {
        $firstMonth = CarbonImmutable::today()->startOfMonth();
        $lastMonth = $firstMonth->addMonth();
        $calendarEndsAt = $lastMonth->endOfMonth();
        $priestRotation->warmPeriod($firstMonth, $calendarEndsAt);
        $scheduledEvents = $this->scheduledEvents(
            $firstMonth,
            $calendarEndsAt,
        );

        return view('client-side.calendar', [
            'pageTitle' => 'Calendar',
            'calendarRange' => $this->calendarRange($firstMonth, $lastMonth),
            'calendarMonths' => [
                $this->calendarMonth($firstMonth, $scheduledEvents, $priestRotation),
                $this->calendarMonth($lastMonth, $scheduledEvents, $priestRotation),
            ],
        ]);
    }

    /**
     * @return array<string, list<array{kind: string, type: string, name: string, time: string, time_value: string, location: string, personnel: ?string, status: string}>>
     */
    private function scheduledEvents(CarbonImmutable $startsAt, CarbonImmutable $endsAt): array
    {
        $appointments = Appointment::query()
            ->select(['service_type', 'preferred_date', 'preferred_time', 'preferred_church', 'confirmed_church', 'starts_at', 'personnel_id'])
            ->with('personnel:id,name')
            ->where('source', OfficeSettings::source())
            ->where('status', 'confirmed')
            ->where(function ($query) use ($startsAt, $endsAt): void {
                $query->whereBetween('starts_at', [$startsAt, $endsAt])
                    ->orWhere(fn ($legacy) => $legacy->whereNull('starts_at')->whereBetween('preferred_date', [$startsAt->toDateString(), $endsAt->toDateString()]));
            })
            ->orderBy('preferred_date')
            ->orderBy('preferred_time')
            ->get();

        $events = [];

        foreach ($appointments as $appointment) {
            $eventDate = $appointment->starts_at ?? $appointment->preferred_date;
            $eventTime = $appointment->starts_at?->format('H:i') ?? $appointment->preferred_time;
            $events[$eventDate->toDateString()][] = [
                'kind' => 'scheduled',
                'type' => 'Service',
                'name' => config(
                    "appointments.services.{$appointment->service_type}.label",
                    Str::headline($appointment->service_type),
                ),
                'time' => $this->formatTime($eventTime),
                'time_value' => mb_substr($eventTime, 0, 5),
                'location' => $appointment->confirmed_church ?? $appointment->preferred_church,
                'personnel' => $appointment->personnel?->name,
                'status' => 'Confirmed',
            ];
        }

        return $events;
    }

    /**
     * @param  array<string, list<array{kind: string, type: string, name: string, time: string, time_value: string, location: string, personnel: ?string, status: string}>>  $scheduledEvents
     * @return array{key: string, label: string, weeks: list<list<array{date: string, date_label: string, day: int, is_today: bool, events: list<array{kind: string, type: string, name: string, time: string, time_value: string, location: string, personnel: ?string, status: string}>}|null>>}
     */
    private function calendarMonth(CarbonImmutable $month, array $scheduledEvents, PriestRotation $priestRotation): array
    {
        $days = array_fill(0, $month->dayOfWeek, null);
        $date = $month->startOfMonth();

        while ($date->format('Y-m') === $month->format('Y-m')) {
            $dateKey = $date->toDateString();
            $events = $this->regularEventsFor($date, $priestRotation);
            array_push($events, ...($scheduledEvents[$dateKey] ?? []));
            usort($events, fn (array $first, array $second): int => [$first['time_value'], $first['name']] <=> [$second['time_value'], $second['name']]);

            $days[] = [
                'date' => $dateKey,
                'date_label' => $date->format('l, F j, Y'),
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
     * @return list<array{kind: string, type: string, name: string, time: string, time_value: string, location: string, personnel: ?string, status: string}>
     */
    private function regularEventsFor(CarbonImmutable $date, PriestRotation $priestRotation): array
    {
        $events = [];

        foreach ($priestRotation->regularMassAssignments($date) as $mass) {
            $events[] = [
                'kind' => 'regular',
                'type' => 'Mass',
                'name' => $mass['title'],
                'location' => $mass['location'],
                'time' => $mass['starts_at']->format('g:i A'),
                'time_value' => $mass['starts_at']->format('H:i'),
                'personnel' => $mass['personnel'],
                'status' => 'Regular schedule',
            ];
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
