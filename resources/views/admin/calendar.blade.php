@extends('admin.layout')
@section('title', 'Calendar & schedules')
@section('actions')
    @can('schedules.manage')<button type="button" class="admin-button" data-calendar-add>+ Add Schedule</button>@endcan
@endsection
@section('content')
<div data-admin-calendar data-feed="{{ route('admin.calendar.events') }}" data-store="{{ route('admin.schedules.store') }}" data-today="{{ $today }}" data-can-manage="{{ auth()->user()->can('schedules.manage') ? '1' : '0' }}" class="grid min-w-0 gap-6">
    <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted"><p>Plan the year. Keep every parish activity in view.</p><p>All times: {{ config('office.timezone') }} · Refreshes every 30 seconds</p></div>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach(['month' => 'Schedules this month', 'upcoming' => 'Upcoming events', 'today' => "Today's schedules", 'completed' => 'Completed this month'] as $key => $label)
            <article class="admin-card"><p class="text-sm text-muted">{{ $label }}</p><p class="mt-2 text-3xl font-semibold" data-summary="{{ $key }}">—</p></article>
        @endforeach
    </div>
    <details class="admin-card" open>
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
    <section class="admin-card min-w-0" aria-label="Schedule calendar">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2"><button type="button" class="admin-secondary" data-calendar-prev aria-label="Previous year">←</button><h2 class="min-w-24 text-center text-2xl" data-calendar-heading></h2><button type="button" class="admin-secondary" data-calendar-next aria-label="Next year">→</button><button type="button" class="admin-secondary" data-calendar-today>Today</button></div>
            <div class="flex gap-1" role="group" aria-label="Calendar view">@foreach(['year', 'month', 'week'] as $view)<button type="button" class="admin-secondary" data-calendar-view="{{ $view }}" aria-pressed="{{ $view === 'year' ? 'true' : 'false' }}">{{ ucfirst($view) }}</button>@endforeach</div>
        </div>
        <div class="mb-4 flex flex-wrap gap-x-4 gap-y-2 text-xs">@foreach(['upcoming', 'ongoing', 'completed', 'cancelled'] as $status)<span class="calendar-status calendar-status-{{ $status }}">{{ ucfirst($status) }}</span>@endforeach<span class="text-muted">Select a day to add a schedule; select an event for details.</span></div>
        <p data-calendar-message role="status" aria-live="polite" class="mb-3 text-sm text-muted">Loading schedules…</p>
        <button type="button" data-calendar-retry class="admin-secondary mb-3" hidden>Retry refresh</button>
        <div data-calendar-grid class="min-w-0" aria-busy="true"></div>
        <p class="mt-4 text-xs text-muted">Appointments and unavailable-time blocks are included. Regular Mass times also reserve the church. Past events use the completed calendar color; appointment completion still requires the existing office workflow.</p>
    </section>
    <section class="admin-card min-w-0" aria-labelledby="upcoming-heading">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2"><div><h2 id="upcoming-heading" class="text-2xl">Upcoming Schedules</h2><p class="mt-1 text-sm text-muted">Ongoing and future events, earliest first. Independent of the displayed year.</p></div><span data-agenda-count class="text-sm text-muted"></span></div>
        <div data-calendar-agenda class="grid gap-3"></div>
        <div class="mt-4 flex items-center justify-between gap-2"><button type="button" class="admin-secondary" data-agenda-prev>Previous</button><span data-agenda-page class="text-sm"></span><button type="button" class="admin-secondary" data-agenda-next>Next</button></div>
    </section>
    <noscript><p class="admin-alert">Enable JavaScript to view and manage the interactive calendar.</p></noscript>
    <dialog data-schedule-dialog class="calendar-dialog" aria-labelledby="schedule-dialog-title">
        <div class="flex items-center justify-between gap-4 border-b border-navy/10 p-5"><h2 id="schedule-dialog-title" class="text-2xl">Schedule details</h2><button type="button" class="admin-secondary" data-dialog-close aria-label="Close schedule details">✕</button></div>
        <div data-schedule-details class="grid gap-4 p-5"></div>
        <form data-schedule-form class="grid gap-4 p-5">
            @csrf
            <p data-schedule-errors role="alert" tabindex="-1" class="calendar-errors" hidden></p>
            <label class="grid gap-1 text-sm">Event title<input class="admin-input" name="title" required maxlength="255"></label>
            <div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1 text-sm">Start date & time<input class="admin-input" name="starts_at" type="datetime-local" required></label><label class="grid gap-1 text-sm">End date & time<input class="admin-input" name="ends_at" type="datetime-local" required></label></div>
            <div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1 text-sm">Location<input class="admin-input" name="location" maxlength="255" list="calendar-locations"></label><label class="grid gap-1 text-sm">Category<input class="admin-input" name="category" required maxlength="80" list="calendar-categories"></label></div>
            <datalist id="calendar-locations">@foreach($churches as $church)<option value="{{ $church }}">@endforeach</datalist>
            <datalist id="calendar-categories">@foreach($categories->merge(['Meeting', 'Liturgy', 'Formation', 'Community', 'Other'])->unique() as $category)<option value="{{ $category }}">@endforeach</datalist>
            <div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1 text-sm">Assigned personnel<select class="admin-input" name="personnel_id"><option value="">Unassigned</option>@foreach($personnel as $person)<option value="{{ $person->id }}" @disabled(! $person->active)>{{ $person->name }}{{ $person->active ? '' : ' (inactive)' }}</option>@endforeach</select></label><label class="grid gap-1 text-sm">Department<input class="admin-input" name="department" maxlength="255"></label></div>
            <label class="grid gap-1 text-sm">Description<textarea class="admin-input" name="description" rows="3" maxlength="10000"></textarea></label>
            <label class="grid gap-1 text-sm">Internal notes<textarea class="admin-input" name="notes" rows="2" maxlength="10000"></textarea></label>
            <label class="grid gap-1 text-sm">Status<select class="admin-input" name="status"><option value="scheduled">Scheduled (upcoming / ongoing by time)</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></label>
            <fieldset data-recurrence-fields class="grid gap-3 rounded-xl border border-navy/10 p-4"><legend class="px-1 text-sm font-semibold">Repeat schedule</legend><label class="grid gap-1 text-sm">Frequency<select class="admin-input" name="recurrence">@foreach(['none' => 'Does not repeat', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label><label data-recurrence-until class="grid gap-1 text-sm" hidden>Repeat through<input class="admin-input" type="date" name="recurrence_until"></label><p class="text-xs text-muted">Up to 366 occurrences. Each occurrence can be edited or cancelled separately. Monthly and yearly schedules use the last valid day when needed.</p></fieldset>
            <p data-occurrence-notice class="text-sm text-muted" hidden>Changes apply only to this occurrence, including rescheduling or cancellation.</p>
            <div class="flex flex-wrap justify-end gap-2"><button type="button" class="admin-secondary" data-dialog-close>Close</button><button type="submit" class="admin-button" data-schedule-save>Save schedule</button></div>
        </form>
    </dialog>
    <dialog data-delete-dialog class="calendar-dialog calendar-confirm" aria-labelledby="delete-schedule-title"><div class="grid gap-4 p-6"><h2 id="delete-schedule-title" class="text-2xl">Delete this schedule?</h2><p>This permanently removes this occurrence. Other occurrences in the series are kept. The audit trail is retained.</p><p data-delete-error role="alert" class="calendar-errors" hidden></p><div class="flex justify-end gap-2"><button type="button" class="admin-secondary" data-delete-dismiss>Keep schedule</button><button type="button" class="admin-button" data-delete-confirm>Delete schedule</button></div></div></dialog>
</div>
@endsection
