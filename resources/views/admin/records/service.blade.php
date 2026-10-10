@extends('admin.layout')
@section('title', $serviceType->name.' records')
@section('actions')
    <a class="admin-secondary" href="{{ route('admin.records.index') }}">All services</a>
    <a class="admin-secondary" href="{{ route('admin.records.all', ['service' => $serviceType->slug]) }}">Search {{ $serviceType->name }}</a>
@endsection
@section('content')
    <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="text-link" href="{{ route('admin.records.index') }}">Service records</a><span aria-hidden="true"> / </span><span aria-current="page">{{ $serviceType->name }}</span></nav>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <p class="text-sm text-muted">Choose a register book to view its {{ Str::lower($serviceType->name) }} records.</p>
        <form method="GET"><label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="archived" value="1" @checked(request('archived')) onchange="this.form.submit()">Include archived records</label></form>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($groups as $group)
            <a href="{{ route('admin.records.book', ['serviceType' => $serviceType, 'book' => $group['name'], 'unassigned' => $group['name'] === null ? 1 : null, 'archived' => request('archived') ?: null]) }}" class="admin-card group grid min-h-40 content-between gap-6 transition hover:-translate-y-0.5 hover:border-gold focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-gold">
                <div>
                    <p class="text-xs font-semibold tracking-[0.16em] text-gold uppercase">{{ $serviceType->name }} register</p>
                    <h2 class="mt-2 text-2xl group-hover:underline group-hover:decoration-gold group-hover:underline-offset-4">{{ $group['name'] ?? 'Unassigned book' }}</h2>
                </div>
                <p class="text-sm text-muted">{{ $group['record_count'] }} {{ Str::plural('record', $group['record_count']) }}@if($group['latest_service_date']) · Latest {{ \Illuminate\Support\Carbon::parse($group['latest_service_date'])->format('M j, Y') }}@endif</p>
            </a>
        @empty
            <p class="admin-card text-sm text-muted sm:col-span-2 xl:col-span-3">No {{ Str::lower($serviceType->name) }} records have been added yet.</p>
        @endforelse
    </div>
@endsection
