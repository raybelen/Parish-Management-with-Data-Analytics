@extends('admin.layout')
@section('title', 'Service records')
@section('actions')
    <a class="admin-secondary" href="{{ route('admin.records.all') }}">Search all records</a>
    @can('records.manage')<a href="{{ route('admin.records.create') }}" class="admin-button">Add historical record</a>@endcan
@endsection
@section('content')
    <p class="text-sm text-muted">Choose a service, then open its physical register book. Records without a book assignment remain available in an Unassigned book group.</p>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($services as $service)
            @php($summary = $serviceSummaries[$service->slug])
            <a href="{{ route('admin.records.service', $service) }}" class="admin-card group grid min-h-40 content-between gap-6 transition hover:-translate-y-0.5 hover:border-gold focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-gold">
                <div>
                    <p class="text-xs font-semibold tracking-[0.16em] text-gold uppercase">Service register</p>
                    <h2 class="mt-2 text-2xl group-hover:underline group-hover:decoration-gold group-hover:underline-offset-4">{{ $service->name }}</h2>
                </div>
                <p class="text-sm text-muted">{{ $summary['record_count'] }} active {{ Str::plural('record', $summary['record_count']) }} · {{ $summary['book_count'] }} {{ Str::plural('book group', $summary['book_count']) }}</p>
            </a>
        @endforeach
    </div>

    @can('records.manage')
        <details class="admin-card"><summary class="cursor-pointer text-lg font-semibold">Import older parish records</summary><p class="my-4 text-sm text-muted">Use the CSV template. Up to 1,000 records per file. The entire import is rejected if any row is invalid or a record number is duplicated.</p><a class="text-link" href="{{ route('admin.records.template') }}">Download CSV template</a><form method="POST" action="{{ route('admin.records.import') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 sm:grid-cols-2" data-confirm="Import these records as preserved historical entries?">@csrf<x-admin.field label="CSV file" name="file" type="file" accept=".csv" required /><x-admin.field label="Source register / import reason" name="reason" required /><button class="admin-button">Import historical records</button></form></details>
    @endcan
@endsection
