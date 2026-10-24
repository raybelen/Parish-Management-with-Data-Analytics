<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Support\AdminCalendar;
use App\Support\OfficeAccess;
use App\Support\OfficeSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminCalendarController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('schedules.view');

        return view('admin.calendar', [
            'personnel' => Personnel::query()->orderBy('name')->get(['id', 'name', 'active']),
            'categories' => OfficeAccess::schedules($request->user())->distinct()->orderBy('category')->pluck('category')
                ->merge($request->user()->can('appointments.view') ? OfficeAccess::appointments($request->user())->distinct()->pluck('service_type')->map(fn (string $type): string => ucfirst($type)) : [])
                ->push('Mass')->unique()->sort()->values(),
            'today' => CarbonImmutable::now(config('office.timezone'))->toDateString(),
            'churches' => OfficeSettings::activeVenues(),
        ]);
    }

    public function events(Request $request, AdminCalendar $calendar): JsonResponse
    {
        Gate::authorize('schedules.view');
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before:2101-01-01'],
            'to' => ['required', 'date_format:Y-m-d', Rule::when(is_string($request->input('from')), 'after:from'), 'before:2102-01-01'],
            'search' => ['nullable', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['upcoming', 'ongoing', 'completed', 'cancelled'])],
            'personnel_id' => ['nullable', 'integer', 'exists:personnel,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('date_from') && is_string($request->input('date_from')), 'after_or_equal:date_from')],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $from = CarbonImmutable::parse($data['from'], config('office.timezone'));
        $to = CarbonImmutable::parse($data['to'], config('office.timezone'));
        abort_if($from->diffInDays($to) > 370, 422, 'Choose a calendar range of at most one year.');
        $now = CarbonImmutable::now(config('office.timezone'));
        $events = $calendar->filter($calendar->events($request->user(), $from, $to), $data);
        $current = $calendar->events($request->user(), $now->startOfMonth(), $now->addMonthNoOverflow()->startOfMonth());
        $future = $calendar->events($request->user(), $now, null)->whereIn('display_status', ['upcoming', 'ongoing'])->values();
        $agenda = $calendar->filter($future->where('kind', '!=', 'Regular Mass'), $data);
        $page = $request->integer('page', 1);

        return response()->json([
            'events' => $events,
            'upcoming' => $agenda->slice(($page - 1) * 20, 20)->values(),
            'upcoming_total' => $agenda->count(), 'page' => $page,
            'summary' => [
                'month' => $current->count(),
                'upcoming' => $future->where('display_status', 'upcoming')->count(),
                'today' => $current->filter(fn (array $event): bool => $event['starts_at'] < $now->addDay()->startOfDay()->format('Y-m-d\TH:i') && $event['ends_at'] > $now->startOfDay()->format('Y-m-d\TH:i') && $event['display_status'] !== 'cancelled')->count(),
                'completed' => $current->where('display_status', 'completed')->count(),
            ],
            'today' => $now->toDateString(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
