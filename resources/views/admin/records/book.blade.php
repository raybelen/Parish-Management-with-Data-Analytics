@extends('admin.layout')
@section('title', $serviceType->name.' · '.($isUnassigned ? 'Unassigned book' : $book))
@section('actions')
    <a class="admin-secondary" href="{{ route('admin.records.service', $serviceType) }}">{{ $serviceType->name }} books</a>
    @can('records.manage')<a href="{{ route('admin.records.create') }}" class="admin-button">Add historical record</a>@endcan
@endsection
@section('content')
    <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="text-link" href="{{ route('admin.records.index') }}">Service records</a><span aria-hidden="true"> / </span><a class="text-link" href="{{ route('admin.records.service', $serviceType) }}">{{ $serviceType->name }}</a><span aria-hidden="true"> / </span><span aria-current="page">{{ $isUnassigned ? 'Unassigned book' : $book }}</span></nav>
    @if($isUnassigned)<p class="admin-alert border-amber-300 bg-amber-50 text-amber-950"><span class="font-semibold">Book assignment needed</span><span class="text-sm">Open a record and enter the physical register book, page, and entry number after verification.</span></p>@endif
    <form class="admin-card grid items-end gap-4 sm:grid-cols-3" method="GET">
        @if($isUnassigned)<input type="hidden" name="unassigned" value="1">@else<input type="hidden" name="book" value="{{ $book }}">@endif
        <x-admin.field label="Name or record number" name="q" :value="request('q')" />
        <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="archived" value="1" @checked(request('archived'))>Include archived records</label>
        <button class="admin-button">Search this book</button>
    </form>
    @include('admin.records.partials.table', ['showService' => false, 'showBook' => false])
@endsection
