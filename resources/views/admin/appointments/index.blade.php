@extends('admin.layout')
@section('title', request('view') === 'calendar' ? 'Appointment calendar' : 'Appointments')
@section('actions')
    <div class="flex flex-wrap gap-2"><a class="admin-secondary" href="{{ route('admin.appointments.index', array_merge(request()->only(['q', 'service', 'date', 'month']), ['view' => request('view') === 'calendar' ? 'list' : 'calendar'])) }}">{{ request('view') === 'calendar' ? 'List view' : 'Calendar view' }}</a>@can('appointments.manage')<a class="admin-button" href="{{ route('admin.appointments.create') }}">New appointment</a>@endcan</div>
@endsection
@section('content')
    <div class="grid gap-4">
        <p class="text-sm text-muted">Find a booking, check its requirements, or update its schedule.</p>
        <nav aria-label="Appointment shortcuts" class="flex flex-wrap gap-2">
            @foreach(['' => 'All appointments', 'pending' => 'Pending review', 'confirmed' => 'Confirmed'] as $value => $label)
                <a href="{{ route('admin.appointments.index', array_merge(request()->only(['q', 'service', 'date']), ['view' => 'list', 'status' => $value])) }}"
                   @if(request('view') !== 'calendar' && request('status', '') === $value) aria-current="page" @endif
                   @class(['admin-secondary', '!border-navy !bg-navy !text-white' => request('view') !== 'calendar' && request('status', '') === $value])>{{ $label }}</a>
            @endforeach
            <a class="admin-secondary" href="{{ route('admin.appointments.index', ['date' => now()->toDateString()]) }}">Today</a>
        </nav>
    </div>
    @if(request('view') === 'calendar')
        <section class="admin-card overflow-x-auto"><div class="mb-5 flex min-w-160 items-center justify-between gap-4"><a class="admin-secondary" href="{{ route('admin.appointments.index', array_merge(request()->only(['q', 'service', 'date']), ['view' => 'calendar', 'month' => $month->subMonth()->format('Y-m')])) }}">Previous month</a><h2 class="text-2xl">{{ $month->format('F Y') }}</h2><a class="admin-secondary" href="{{ route('admin.appointments.index', array_merge(request()->only(['q', 'service', 'date']), ['view' => 'calendar', 'month' => $month->addMonth()->format('Y-m')])) }}">Next month</a></div>
            <div class="grid min-w-200 grid-cols-7 border-t border-l border-navy/10">@foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)<div class="border-r border-b border-navy/10 bg-slate-50 p-3 text-center text-xs font-semibold">{{ $weekday }}</div>@endforeach
                @for($blank = 0; $blank < $month->dayOfWeek; $blank++)<div class="min-h-28 border-r border-b border-navy/10 bg-slate-50"></div>@endfor
                @for($day = 1; $day <= $month->daysInMonth; $day++)
                    @php($date = $month->day($day))
                    <div class="min-h-28 border-r border-b border-navy/10 p-2" data-calendar-date="{{ $date->toDateString() }}"><p class="mb-2 text-sm font-semibold">{{ $day }}</p>@foreach($calendarAppointments[$date->toDateString()] ?? [] as $event)<a @class(['mb-2 block rounded p-2 text-xs', 'bg-amber-50 text-amber-950' => $event->status === 'pending', 'bg-blue-50 text-blue-950' => $event->status === 'confirmed']) href="{{ route('admin.appointments.show', $event) }}">{{ $event->starts_at->format('g:i A') }} · {{ ucfirst($event->service_type) }}<br>{{ $event->status === 'pending' ? 'Awaiting documents' : 'Confirmed' }}<br>{{ $event->personnel?->name }}<br>{{ $event->confirmed_church }}<br>Venue reserved until {{ $event->reserved_until->format('g:i A') }}</a>@endforeach</div>
                @endfor
            </div><p class="mt-4 text-xs text-muted">This staff calendar shows both three-day document holds and confirmed appointments. Only confirmed appointments appear on the public parish calendar.</p>
        </section>
    @endif
    <form class="admin-card grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-4" method="GET">
        <div class="sm:col-span-2 xl:col-span-4"><h2 class="text-xl">Find appointments</h2></div>
        <input type="hidden" name="view" value="{{ request('view', 'list') }}">
        @if(request('view') === 'calendar')<input type="hidden" name="month" value="{{ $month->format('Y-m') }}">@endif
        <x-admin.field label="Name or reference number" name="q" :value="request('q')" />
        <x-admin.field label="Status" name="status"><select class="admin-input" name="status">@if(request('view') !== 'calendar')<option value="">All statuses</option>@endif @foreach(request('view') === 'calendar' ? ['pending', 'confirmed'] : \App\Models\Appointment::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status === 'pending' ? 'Awaiting documents' : ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></x-admin.field>
        <x-admin.field label="Service" name="service"><select class="admin-input" name="service"><option value="">All services</option>@foreach($services as $service)<option value="{{ $service->slug }}" @selected(request('service') === $service->slug)>{{ $service->name }}</option>@endforeach</select></x-admin.field>
        <x-admin.field label="Schedule date" name="date" type="date" :value="request('date')" />
        <div class="flex flex-wrap items-center gap-3 sm:col-span-2 xl:col-span-4">
            <button class="admin-button">Search appointments</button>
            @if(request()->filled('q') || request()->filled('status') || request()->filled('service') || request()->filled('date'))
                <a class="admin-secondary" href="{{ route('admin.appointments.index', request()->only(['view', 'month'])) }}">Clear filters</a>
            @endif
        </div>
    </form>
    <div class="admin-card overflow-x-auto !p-0"><div class="border-b border-navy/10 px-5 py-4"><h2 class="text-xl">{{ number_format($appointments->total()) }} {{ $appointments->total() === 1 ? 'appointment' : 'appointments' }}</h2><p class="mt-1 text-sm text-muted">Open an appointment to review documents, payments, and next steps.</p></div><div class="grid divide-y divide-navy/10 lg:hidden">
        @forelse($appointments as $appointment)
            <article class="grid gap-4 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0"><h3 class="break-words text-lg">{{ $appointment->client_full_name }}</h3><p class="mt-1 text-xs text-muted">{{ $appointment->reference_number }}</p></div>
                    <x-admin.status :value="$appointment->status" />
                </div>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-muted">Service</dt><dd class="mt-1">{{ ucfirst($appointment->service_type) }}</dd></div>
                    <div><dt class="text-xs text-muted">Date & time</dt><dd class="mt-1">@if($appointment->starts_at){{ $appointment->starts_at->format('M j, Y ? g:i A') }}<span class="mt-1 block text-xs text-muted">Ends {{ $appointment->ends_at->format('g:i A') }} ? venue reserved until {{ $appointment->reserved_until->format('g:i A') }}</span>@else{{ $appointment->preferred_date->format('M j, Y') }} ? {{ \Carbon\Carbon::parse($appointment->preferred_time)->format('g:i A') }}<span class="mt-1 block text-xs text-muted">Requested; not confirmed</span>@endif</dd></div>
                    <div><dt class="text-xs text-muted">Location</dt><dd class="mt-1">{{ $appointment->confirmed_church ?? $appointment->preferred_church }}</dd></div>
                    <div><dt class="text-xs text-muted">Priest / staff</dt><dd class="mt-1">{{ $appointment->personnel?->name ?? 'Not assigned yet' }}</dd></div>
                </dl>
                <a class="admin-secondary" href="{{ route('admin.appointments.show', $appointment) }}" aria-label="Open appointment {{ $appointment->reference_number }}">Open appointment</a>
            </article>
        @empty
            <p class="p-6 text-sm text-muted">No appointments found. Try another name, date, or status, or clear the filters.</p>
        @endforelse
    </div><table class="admin-table hidden lg:table"><thead><tr><th>Reference / contact</th><th>Service</th><th>Schedule</th><th>Priest / staff & location</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
        @forelse($appointments as $appointment)<tr><td><a class="font-semibold underline decoration-gold underline-offset-4" href="{{ route('admin.appointments.show', $appointment) }}">{{ $appointment->reference_number }}</a><p class="mt-2">{{ $appointment->client_full_name }}</p></td><td>{{ ucfirst($appointment->service_type) }}</td><td>@if($appointment->starts_at){{ $appointment->starts_at->format('M j, Y · g:i A') }}<br><span class="text-xs text-muted">Ends {{ $appointment->ends_at->format('g:i A') }} · venue reserved until {{ $appointment->reserved_until->format('g:i A') }}</span>@else{{ $appointment->preferred_date->format('M j, Y') }} · {{ substr($appointment->preferred_time, 0, 5) }}<br><span class="text-xs text-muted">Requested; not confirmed</span>@endif</td><td>{{ $appointment->personnel?->name ?? 'Unassigned' }}<br><span class="text-xs text-muted">{{ $appointment->confirmed_church ?? $appointment->preferred_church }}</span></td><td><x-admin.status :value="$appointment->status" /></td><td><a class="admin-secondary whitespace-nowrap" aria-label="Open appointment {{ $appointment->reference_number }}" href="{{ route('admin.appointments.show', $appointment) }}">Open appointment</a></td></tr>@empty<tr><td colspan="6" class="text-center text-muted">No appointments found. Try another name, date, or status, or clear the filters.</td></tr>@endforelse
    </tbody></table></div>
    {{ $appointments->links() }}
    @can('settings.manage')
        <details class="admin-card"><summary class="cursor-pointer text-lg font-semibold">Mark a location or staff member unavailable</summary>
            <form class="mt-5 grid gap-4 sm:grid-cols-2" method="POST" action="{{ route('admin.blocks.store') }}">@csrf
                <x-admin.field label="Reason / event" name="title" required />
                <x-admin.field label="Venue (choose venue, personnel, or both)" name="church"><select name="church" class="admin-input"><option value="">No venue restriction</option>@foreach($churches as $church)<option>{{ $church }}</option>@endforeach</select></x-admin.field>
                <x-admin.field label="Unavailable priest / staff" name="personnel_id"><select name="personnel_id" class="admin-input"><option value="">No staff restriction</option>@foreach($personnel as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></x-admin.field>
                <x-admin.field label="Start" name="starts_at" type="datetime-local" required /><x-admin.field label="End, including preparation" name="ends_at" type="datetime-local" required />
                <button class="admin-button sm:col-span-2">Save unavailable time</button>
            </form>
        </details>
        @if($blocks->isNotEmpty())<section class="admin-card"><h2 class="mb-4 text-2xl">Unavailable times</h2>@foreach($blocks as $block)<div class="grid gap-3 border-b border-navy/10 py-4 sm:grid-cols-2"><p class="text-sm"><strong>{{ $block->title }}</strong><br>{{ $block->church ?? $block->personnel?->name }}<br>{{ $block->starts_at->format('M j, g:i A') }} – {{ $block->ends_at->format('M j, g:i A') }}</p><form class="flex items-end gap-2" method="POST" action="{{ route('admin.blocks.destroy', $block) }}" data-confirm="Remove this unavailable time?">@csrf @method('DELETE')<x-admin.field label="Removal reason" name="reason" required /><button class="admin-secondary">Remove</button></form></div>@endforeach</section>@endif
    @endcan
@endsection
