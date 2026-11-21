@extends('admin.layout')
@section('title', $serviceType->name.' · '.($isUnassigned ? 'Book 1' : $book))
@section('actions')
    <a class="admin-secondary" href="{{ route('admin.records.service', $serviceType) }}">{{ $serviceType->name }} books</a>
    @can('records.manage')<a href="{{ route('admin.records.create', ['service_type' => $serviceType->slug, 'register_book' => $book ?: 'Book 1']) }}" class="admin-button">Add historical record</a>@endcan
@endsection
@section('content')
    <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="text-link" href="{{ route('admin.records.index') }}">Service records</a><span aria-hidden="true"> / </span><a class="text-link" href="{{ route('admin.records.service', $serviceType) }}">{{ $serviceType->name }}</a><span aria-hidden="true"> / </span><span aria-current="page">{{ $isUnassigned ? 'Book 1' : $book }}</span></nav>
    <form class="admin-card grid gap-4" method="GET" role="search" aria-label="Search this register">
        @if($isUnassigned)<input type="hidden" name="unassigned" value="1">@else<input type="hidden" name="book" value="{{ $book }}">@endif
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1 basis-64"><x-admin.field label="Find a record" name="q" :value="request('q')" placeholder="Enter a name or record number" /></div>
            <button class="admin-button flex-1 sm:flex-none">Search this book</button>
            @if(request()->filled('q') || request()->boolean('archived'))
                <a class="admin-secondary" href="{{ route('admin.records.book', ['serviceType' => $serviceType, 'book' => $isUnassigned ? null : $book, 'unassigned' => $isUnassigned ? 1 : null]) }}">Clear filters</a>
            @endif
        </div>
        <details class="border-t border-navy/10 pt-3" @if(request()->boolean('archived')) open @endif>
            <summary class="w-fit cursor-pointer text-sm font-medium">More filters @if(request()->boolean('archived'))<span class="ml-2 rounded-full bg-ivory px-2 py-0.5 text-xs text-navy">1 active</span>@endif</summary>
            <label class="mt-2 flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="archived" value="1" @checked(request('archived'))>Include archived records</label>
        </details>
    </form>
    @if(\App\Support\ServiceRegister::columns($serviceType->slug))
        @include('admin.records.partials.register-table', ['columns' => \App\Support\ServiceRegister::columns($serviceType->slug)])
    @else
        @include('admin.records.partials.table', ['showService' => false, 'showBook' => false])
    @endif
@endsection
