@extends('admin.layout')
@section('title', 'Office overview')
@section('actions')@can('appointments.manage')<a class="admin-button" href="{{ route('admin.appointments.create') }}">New walk-in appointment</a>@endcan
    @can('schedules.view')<a class="admin-secondary" href="{{ route('admin.calendar.index') }}">Calendar & schedules</a>@endcan
@endsection
@section('content')
    <p class="text-sm text-muted">Welcome, {{ auth()->user()->name }}. Review requests and upcoming services below.</p>
    @if($notifications->isNotEmpty())
        <section class="admin-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-2xl">Needs attention</h2>
                <a class="text-link" href="{{ route('admin.notifications.index') }}">Open office inbox</a>
            </div>
            @foreach($notifications->take(3) as $notification)<p class="border-b border-navy/10 py-3 text-sm last:border-0">{{ $notification->message }}</p>@endforeach
            @if($notifications->count() > 3)
                <details class="mt-3">
                    <summary class="cursor-pointer py-2 text-sm font-medium">Show {{ $notifications->count() - 3 }} more notices</summary>
                    @foreach($notifications->skip(3) as $notification)<p class="border-b border-navy/10 py-3 text-sm last:border-0">{{ $notification->message }}</p>@endforeach
                </details>
            @endif
        </section>
    @endif
    @can('appointments.view')
    <section aria-label="Appointment totals" class="grid gap-3">
    <p class="text-xs text-muted">Appointment totals · All dates</p>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach(['pending' => 'Awaiting review', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $status => $label)
            <a href="{{ route('admin.appointments.index', ['status' => $status]) }}" class="admin-card"><p class="text-sm text-muted">{{ $label }}</p><p class="mt-2 text-3xl font-semibold tabular-nums">{{ $counts[$status] ?? 0 }}</p></a>
        @endforeach
    </div>
    </section>
    @endcan
    @can('appointments.view')
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="admin-card"><h2 class="mb-5 text-2xl">Upcoming services</h2><div class="grid gap-4">
            @forelse($upcoming as $appointment)<a class="flex justify-between gap-4 border-b border-navy/10 pb-4 text-sm" href="{{ route('admin.appointments.show', $appointment) }}"><span><strong>{{ ucfirst($appointment->service_type) }}</strong><br>{{ $appointment->client_full_name }}<br><span class="text-muted">{{ $appointment->personnel?->name }}</span></span><span class="text-right">{{ $appointment->starts_at->format('M j, Y') }}<br>{{ $appointment->starts_at->format('g:i A') }}<br><span class="text-xs text-muted">Prepared through {{ $appointment->reserved_until->format('g:i A') }}</span></span></a>@empty<p class="text-sm text-muted">No upcoming confirmed services.</p>@endforelse
        </div></section>
        <section class="admin-card"><h2 class="mb-5 text-2xl">Appointments by service</h2><div class="grid gap-3">@forelse($services as $service => $total)<div class="flex justify-between border-b border-navy/10 pb-3 text-sm"><span>{{ ucfirst($service) }}</span><strong>{{ $total }}</strong></div>@empty<p class="text-sm text-muted">Service totals will appear as requests arrive.</p>@endforelse</div></section>
    </div>
    @endcan
    @if($totals)
        <details class="admin-card">
            <summary class="cursor-pointer text-lg font-semibold">Financial summary <span class="ml-2 text-sm font-normal text-muted">Collections, refunds & balances</span></summary>
        <div class="mt-5 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            @foreach(['cash_collected' => 'Collected this month', 'refunds' => 'Refunded this month', 'completed_revenue' => 'Completed-service collections', 'outstanding' => 'Outstanding · all dates'] as $key => $label)
                <div class="min-w-0"><p class="text-sm text-muted">{{ $label }}</p><p class="mt-2 break-words text-2xl font-semibold tabular-nums">₱{{ number_format($totals[$key], 2) }}</p></div>
            @endforeach
        </div>
        <p class="mt-5 text-xs leading-5 text-muted">Collections and refunds use payment dates. Completed-service collections use completion dates and net payments recorded to date. Historical imports are excluded.</p>
        </details>
    @endif
    @if(config('office.analytics_enabled'))
        @can('reports.view')
        <section class="admin-card">
            <div class="flex flex-wrap items-center justify-between gap-4"><h2 class="text-2xl">Parish historical statistics</h2><a class="text-link" href="{{ route('admin.statistics.index') }}">Review monthly totals</a></div>
            @if($annualStatistics)
                @php($annualSummary = $annualStatistics[0])
                <p class="mt-3 text-sm text-muted">Recorded parish activity in {{ $annualSummary['year'] }}.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    @foreach(\App\Support\ParishStatistics::FORECAST_SERVICES as $code)
                        <div class="rounded-lg bg-slate-50 p-4"><p class="text-sm text-muted">{{ \App\Support\ParishStatistics::SERVICES[$code] }}</p><p class="mt-2 text-2xl">{{ $annualSummary['services'][$code]['total'] ?? 'Unavailable' }}</p><p class="mt-2 text-xs text-muted">{{ $annualSummary['services'][$code]['months'] === 12 ? 'Full-year total' : $annualSummary['services'][$code]['months'].' of 12 months recorded' }}</p></div>
                    @endforeach
                </div>
            @else<p class="mt-4 text-sm text-muted">Import the verified reports or enter confirmed monthly totals to begin.</p>@endif
        </section>
        @endcan
        @can('forecasts.manage')
        <section class="admin-card"><h2 class="text-2xl">Service forecasts</h2><p class="my-3 text-sm text-muted">Explore estimated monthly activity to help plan parish services.</p><div class="flex flex-wrap gap-3">@foreach(\App\Support\ParishStatistics::FORECAST_SERVICES as $code)<a class="admin-secondary" href="{{ route('admin.forecasts.index', ['service' => $code]) }}">{{ \App\Support\ParishStatistics::SERVICES[$code] }}</a>@endforeach</div></section>
        @endcan
    @endif
    @if($activity->isNotEmpty())<details class="admin-card"><summary class="cursor-pointer text-lg font-semibold">Recent office activity</summary><div class="mt-4">@foreach($activity as $entry)<p class="border-b border-navy/10 py-3 text-sm">{{ \Illuminate\Support\Str::headline($entry->action) }} · {{ $entry->actor?->name ?? 'Client / system' }} · {{ $entry->created_at->format('M j, g:i A') }}</p>@endforeach<a class="text-link mt-4" href="{{ route('admin.audit.index') }}">Open audit trail</a></div></details>@endif
@endsection
