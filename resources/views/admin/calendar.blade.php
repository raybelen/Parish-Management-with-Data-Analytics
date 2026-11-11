@extends('admin.layout')
@section('title', 'Calendar & schedules')
@section('content')
<div data-admin-calendar data-feed="{{ route('admin.calendar.events') }}" data-store="{{ route('admin.schedules.store') }}" data-today="{{ $today }}" data-can-manage="{{ auth()->user()->can('schedules.manage') ? '1' : '0' }}" class="grid min-w-0 gap-6">
    <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted"><p>Plan the year. Keep every parish activity in view.</p><p>All times: {{ config('office.timezone') }} · Refreshes every 30 seconds</p></div>
    <div class="order-2 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach(['month' => 'Schedules this month', 'upcoming' => 'Upcoming events', 'today' => "Today's schedules", 'completed' => 'Completed this month'] as $key => $label)
            <article class="admin-card"><p class="text-sm text-muted">{{ $label }}</p><p class="mt-2 text-3xl font-semibold" data-summary="{{ $key }}">—</p></article>
        @endforeach
    </div>
    <details class="admin-card">
        <summary class="cursor-pointer font-semibold">Search & filters</summary>
        <form data-calendar-filters class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <label class="grid gap-1 text-sm">Search<input class="admin-input" name="search" type="search" maxlength="255" placeholder="Title, location, department…"></label>
            <label class="grid gap-1 text-sm">Category<select class="admin-input" name="category"><option value="">All categories</option>@foreach($categories->merge(['Meeting', 'Liturgy', 'Formation', 'Community', 'Other', 'Unavailable time'])->merge(collect(config('appointments.services'))->pluck('label'))->unique()->sort() as $category)<option>{{ $category }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-sm">Status<select class="admin-input" name="status"><option value="">All statuses</option>@foreach(['upcoming', 'ongoing', 'completed', 'cancelled'] as $status)<option value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-sm">From date<input class="admin-input" type="date" name="date_from"></label>
            <label class="grid gap-1 text-sm">Through date<input class="admin-input" type="date" name="date_to"></label>
            <label class="grid gap-1 text-sm">Assigned personnel<select class="admin-input" name="personnel_id"><option value="">All personnel</option>@foreach($personnel as $person)<option value="{{ $person->id }}">{{ $person->name }}{{ $person->active ? '' : ' (inactive)' }}</option>@endforeach</select></label>
            <div class="flex flex-wrap gap-2 sm:col-span-2 xl:col-span-3"><button class="admin-button">Apply filters</button><button type="reset" class="admin-secondary">Clear filters</button><span class="self-center text-xs text-muted">Filters apply to the calendar and upcoming list. Summary cards show all accessible schedules.</span></div>
        </form>
    </details>
    <section class="admin-card calendar-board min-w-0" aria-label="Schedule calendar">
        <div class="calendar-toolbar">
            <div class="min-w-0"><h2 class="text-2xl" data-calendar-heading>Calendar</h2><p class="mt-1 text-xs text-muted" data-calendar-range></p></div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="calendar-navigation"><button type="button" class="admin-secondary" data-calendar-prev aria-label="Previous month">←</button><button type="button" class="admin-secondary" data-calendar-today>Today</button><button type="button" class="admin-secondary" data-calendar-next aria-label="Next month">→</button></div>
                <select class="admin-input w-auto" data-calendar-view-select aria-label="Calendar view">@foreach(['month', 'week', 'year'] as $view)<option value="{{ $view }}">{{ ucfirst($view) }} view</option>@endforeach</select>
                @can('schedules.manage')<button type="button" class="admin-button" data-calendar-add>+ Add Schedule</button>@endcan
            </div>
        </div>
        <div class="mb-4 flex flex-wrap gap-x-4 gap-y-2 text-xs">@foreach(['upcoming', 'ongoing', 'completed', 'cancelled'] as $status)<span class="calendar-status calendar-status-{{ $status }}">{{ ucfirst($status) }}</span>@endforeach<span class="text-muted">Select a day to add a schedule; select an event for details.</span></div>
        <p data-calendar-message role="status" aria-live="polite" class="mb-3 text-sm text-muted">Loading schedules…</p>
        <button type="button" data-calendar-retry class="admin-secondary mb-3" hidden>Retry refresh</button>
        <div data-calendar-skeleton class="calendar-month-scroll" aria-hidden="true">
            <div class="calendar-skeleton">
                @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
                    <span class="calendar-skeleton-weekday">{{ $day }}</span>
                @endforeach
                @foreach(range(1, 42) as $cell)
                    <div class="calendar-skeleton-cell">
                        <span class="calendar-skeleton-date"></span>
                        @if($cell % 3 !== 0)<span class="calendar-skeleton-event"></span>@endif
                        @if($cell % 7 === 2)<span class="calendar-skeleton-event calendar-skeleton-event-short"></span>@endif
                    </div>
                @endforeach
            </div>
        </div>
        <div data-calendar-grid class="min-w-0" aria-busy="true" hidden></div>
        <p class="mt-4 text-xs text-muted">Year markings show appointments and staff-created activities only. Regular Mass times and unavailable-time blocks remain visible in Month and Week views and still reserve their time and location. Past events use the completed calendar color; appointment completion still requires the existing office workflow.</p>
    </section>
    <section class="admin-card order-3 min-w-0" aria-labelledby="upcoming-heading">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2"><div><h2 id="upcoming-heading" class="text-2xl">Upcoming Schedules</h2><p class="mt-1 text-sm text-muted">Ongoing and future services and activities, earliest first. Regular Masses appear in the calendar only. Independent of the displayed year.</p></div><span data-agenda-count class="text-sm text-muted"></span></div>
        <div data-calendar-agenda class="grid gap-3"></div>
        <div class="mt-4 flex items-center justify-between gap-2"><button type="button" class="admin-secondary" data-agenda-prev>Previous</button><span data-agenda-page class="text-sm"></span><button type="button" class="admin-secondary" data-agenda-next>Next</button></div>
    </section>
    <noscript><p class="admin-alert">Enable JavaScript to view and manage the interactive calendar.</p></noscript>
    @include('admin.partials.schedule-dialog')
    <dialog data-delete-dialog class="calendar-dialog calendar-confirm" aria-labelledby="delete-schedule-title"><div class="grid gap-4 p-6"><h2 id="delete-schedule-title" class="text-2xl">Delete this schedule?</h2><p>This permanently removes this occurrence. Other occurrences in the series are kept. The audit trail is retained.</p><p data-delete-error role="alert" class="calendar-errors" hidden></p><div class="flex justify-end gap-2"><button type="button" class="admin-secondary" data-delete-dismiss>Keep schedule</button><button type="button" class="admin-button" data-delete-confirm>Delete schedule</button></div></div></dialog>
</div>
@endsection
