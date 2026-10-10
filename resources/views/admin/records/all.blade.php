@extends('admin.layout')
@section('title', 'All service records')
@section('actions')
    <a class="admin-secondary" href="{{ route('admin.records.index') }}">Browse by service</a>
    @can('records.manage')<a href="{{ route('admin.records.create') }}" class="admin-button">Add historical record</a>@endcan
@endsection
@section('content')
    <p class="text-sm text-muted">Search completed services and preserved entries from older parish registers across every service and book.</p>
    <form class="admin-card grid items-end gap-4 sm:grid-cols-4" method="GET"><x-admin.field label="Name or record number" name="q" :value="request('q')" /><x-admin.field label="Service" name="service"><select class="admin-input" name="service"><option value="">All services</option>@foreach($services as $service)<option value="{{ $service->slug }}" @selected(request('service') === $service->slug)>{{ $service->name }}</option>@endforeach</select></x-admin.field><label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="archived" value="1" @checked(request('archived'))>Include archived records</label><button class="admin-button">Search records</button></form>
    @include('admin.records.partials.table', ['showService' => true, 'showBook' => true])
@endsection
