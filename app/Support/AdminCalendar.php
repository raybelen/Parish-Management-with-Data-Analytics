<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminCalendar
{
    public function __construct(private PriestRotation $priestRotation) {}

    /** @return Collection<int, array<string, mixed>> */
    public function events(User $user, CarbonImmutable $from, ?CarbonImmutable $to): Collection
    {
        $schedules = $this->range(OfficeAccess::schedules($user), $from, $to)->with('personnel')->get()
            ->map(fn (Schedule $schedule): array => [
                ...$schedule->only(['title', 'description', 'notes', 'location', 'department', 'category', 'personnel_id', 'priest_required', 'status', 'version', 'recurrence', 'series_id', 'group_capacity', 'group_fee']),
                'id' => 'schedule-'.$schedule->id,
                'starts_at' => $schedule->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $schedule->ends_at->format('Y-m-d\TH:i'),
                'personnel' => $schedule->personnel?->name,
                'recurrence_until' => $schedule->recurrence_until?->toDateString(),
                'edit_url' => $user->can('schedules.manage') ? route('admin.schedules.update', $schedule) : null,
                'detail_url' => null,
                'kind' => 'Schedule',
                'assignment_status' => $schedule->priest_required && $schedule->personnel_id === null ? 'needs_reassignment' : 'assigned',
            ]);
        $appointments = collect();
        if ($user->can('appointments.view')) {
            $appointments = $this->range(OfficeAccess::appointments($user), $from, $to)
                ->whereIn('status', ['pending', 'confirmed', 'completed', 'cancelled'])->whereNotNull('ends_at')->with('personnel')->get()
                ->map(fn (Appointment $appointment): array => [
                    'id' => 'appointment-'.$appointment->id, 'title' => ucfirst($appointment->service_type).' · '.$appointment->reference_number,
                    'starts_at' => $appointment->starts_at->format('Y-m-d\TH:i'), 'ends_at' => $appointment->ends_at->format('Y-m-d\TH:i'),
                    'status' => in_array($appointment->status, ['pending', 'confirmed'], true) ? 'scheduled' : $appointment->status,
                    'location' => $appointment->confirmed_church, 'personnel_id' => $appointment->personnel_id,
                    'personnel' => $appointment->personnel?->name, 'department' => null, 'category' => ucfirst($appointment->service_type),
                    'description' => $appointment->status === 'pending'
                        ? 'Awaiting physical document verification before public confirmation.'
                        : 'Manage this appointment through its existing office workflow.',
                    'notes' => $appointment->status === 'pending' ? 'Awaiting documents' : null,
                    'edit_url' => null, 'detail_url' => route('admin.appointments.show', $appointment), 'kind' => 'Appointment',
                    'priest_required' => true, 'assignment_status' => $appointment->personnel_id === null ? 'needs_reassignment' : 'assigned',
                ]);
        }
        $blocks = $this->range(ScheduleBlock::query(), $from, $to)
            ->when($user->role === 'priest', fn (Builder $query) => $query->whereHas('personnel', fn (Builder $personnel) => $personnel->where('user_id', $user->id)))
            ->with('personnel')->get()->map(fn (ScheduleBlock $block): array => [
                'id' => 'block-'.$block->id, 'title' => $block->title,
                'starts_at' => $block->starts_at->format('Y-m-d\TH:i'), 'ends_at' => $block->ends_at->format('Y-m-d\TH:i'),
                'status' => 'scheduled', 'location' => $block->church, 'personnel_id' => $block->personnel_id,
                'personnel' => $block->personnel?->name, 'department' => null, 'category' => 'Unavailable time',
                'description' => 'Unavailable-time block. Manage this entry in Parish settings.', 'notes' => null,
                'edit_url' => null, 'detail_url' => $user->can('settings.manage') ? route('admin.settings.index') : null, 'kind' => 'Unavailable time',
            ]);

        return $schedules->concat($appointments)->concat($blocks)->concat($this->regularMasses($from, $to ?? $from->addYear()))->map(function (array $event): array {
            $now = CarbonImmutable::now(config('office.timezone'))->format('Y-m-d\TH:i');
            $event['display_status'] = match (true) {
                $event['status'] === 'cancelled' => 'cancelled',
                $event['status'] === 'completed', $event['ends_at'] <= $now => 'completed',
                $event['starts_at'] <= $now => 'ongoing',
                default => 'upcoming',
            };

            return $event;
        })->sortBy([['starts_at', 'asc'], ['id', 'asc']])->values();
    }

    /** @param Collection<int, array<string, mixed>> $events
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function filter(Collection $events, array $filters): Collection
    {
        return $events->filter(function (array $event) use ($filters): bool {
            return (empty($filters['search']) || str_contains(mb_strtolower(implode(' ', array_intersect_key($event, array_flip(['title', 'description', 'location', 'personnel', 'department', 'category'])))), mb_strtolower($filters['search'])))
                && (empty($filters['category']) || $event['category'] === $filters['category'])
                && (empty($filters['status']) || $event['display_status'] === $filters['status'])
                && (empty($filters['personnel_id']) || (int) $event['personnel_id'] === (int) $filters['personnel_id'])
                && (empty($filters['date_from']) || $event['ends_at'] > $filters['date_from'].'T00:00')
                && (empty($filters['date_to']) || $event['starts_at'] < CarbonImmutable::parse($filters['date_to'])->addDay()->format('Y-m-d\TH:i'));
        })->values();
    }

    private function range(Builder $query, CarbonImmutable $from, ?CarbonImmutable $to): Builder
    {
        return $query->where('ends_at', '>', $from)->when($to, fn (Builder $query) => $query->where('starts_at', '<', $to));
    }

    /** @return list<array<string, mixed>> */
    private function regularMasses(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $this->priestRotation->warmPeriod($from, $to);
        $events = [];
        for ($date = $from->startOfDay(); $date < $to; $date = $date->addDay()) {
            foreach ($this->priestRotation->regularMassAssignments($date) as $mass) {
                $start = $mass['starts_at'];
                $end = $mass['ends_at'];
                if ($end <= $from || $start >= $to) {
                    continue;
                }
                $events[] = [
                    'id' => 'mass-'.$mass['key'],
                    'title' => $mass['title'],
                    'starts_at' => $start->format('Y-m-d\TH:i'),
                    'ends_at' => $end->format('Y-m-d\TH:i'),
                    'location' => $mass['location'],
                    'status' => 'scheduled', 'category' => 'Mass', 'kind' => 'Regular Mass',
                    'personnel_id' => $mass['personnel_id'], 'personnel' => $mass['personnel'], 'department' => null,
                    'priest_required' => true, 'assignment_status' => $mass['assignment_status'],
                    'description' => 'Regular weekly Mass. The venue remains reserved after Mass.',
                    'notes' => null, 'edit_url' => null, 'detail_url' => null,
                ];
            }
        }

        return $events;
    }
}
