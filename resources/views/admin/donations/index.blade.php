@extends('admin.layout')
@section('title', 'Donations')
@section('actions')
    @can('donations.manage')<a class="admin-secondary" href="{{ route('admin.donations.create', ['kind' => 'mass_collection']) }}">Record Mass collection</a><a class="admin-button" href="{{ route('admin.donations.create') }}">Record donation</a>@endcan
@endsection
@section('content')
    <p class="max-w-3xl text-sm leading-6 text-muted">Track donations received by the parish, find donor receipts, and review collection summaries. All amounts are in PHP. These totals are separate from appointment payments.</p>
    @php
        $activeFilters = collect($filters)->except('export')->filter(fn (mixed $value): bool => filled($value));
        $additionalFilterCount = $activeFilters->only(['method', 'status', 'review', 'kind', 'church', 'collection_type'])->count();
    @endphp
    <form method="GET" action="{{ route('admin.donations.index') }}" class="admin-card space-y-5" aria-labelledby="donation-filters-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="donation-filters-heading" class="font-sans text-sm font-semibold">Filter donations</h2>
            @if($activeFilters->isNotEmpty())
                <span class="rounded-full bg-navy/5 px-3 py-1 text-xs font-medium text-navy">{{ $activeFilters->count() }} active {{ $activeFilters->count() === 1 ? 'filter' : 'filters' }}</span>
            @endif
        </div>
        <div class="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="sm:col-span-2"><x-admin.field label="Search donations" name="q" :value="$filters['q'] ?? ''" placeholder="Donor, receipt, contact or reference" maxlength="255" /></div>
            <x-admin.field label="From" name="from" type="date" :value="$filters['from'] ?? ''" />
            <x-admin.field label="To" name="to" type="date" :value="$filters['to'] ?? ''" />
            <div class="sm:col-span-2 xl:col-span-1"><x-admin.field label="Category" name="category"><select class="admin-input" name="category"><option value="">All categories</option>@foreach(\App\Models\Donation::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected(($filters['category'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></x-admin.field></div>
        </div>
        <details class="group border-t border-navy/10 pt-2" @if($additionalFilterCount > 0 || $errors->any()) open @endif>
            <summary class="flex min-h-11 cursor-pointer list-none flex-wrap items-center gap-2 text-sm font-semibold [&::-webkit-details-marker]:hidden">
                <svg class="size-4 shrink-0 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                More filters
                @if($additionalFilterCount > 0)
                    <span class="rounded-full bg-navy/5 px-2 py-0.5 text-xs font-medium">{{ $additionalFilterCount }} active</span>
                @endif
                <span class="w-full pl-6 text-xs font-normal text-muted sm:w-auto sm:pl-1">Payment, status and collection details</span>
            </summary>
            <div class="grid gap-6 pb-1 pt-4 xl:grid-cols-2">
                <fieldset class="min-w-0">
                    <legend class="mb-3 text-xs font-semibold tracking-wide text-muted uppercase">Payment &amp; review</legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-admin.field label="Payment method" name="method"><select class="admin-input" name="method"><option value="">All methods</option>@foreach(\App\Models\Donation::METHODS as $key => $label)<option value="{{ $key }}" @selected(($filters['method'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></x-admin.field>
                        <x-admin.field label="Status" name="status"><select class="admin-input" name="status"><option value="">All statuses</option><option value="recorded" @selected(($filters['status'] ?? '') === 'recorded')>Recorded</option><option value="voided" @selected(($filters['status'] ?? '') === 'voided')>Canceled</option></select></x-admin.field>
                        <x-admin.field label="Review" name="review"><select class="admin-input" name="review"><option value="">All records</option><option value="pending" @selected(($filters['review'] ?? '') === 'pending')>Awaiting approval</option></select></x-admin.field>
                    </div>
                </fieldset>
                <fieldset class="min-w-0">
                    <legend class="mb-3 text-xs font-semibold tracking-wide text-muted uppercase">Record &amp; collection</legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-admin.field label="Record type" name="kind"><select class="admin-input" name="kind"><option value="">All types</option><option value="individual" @selected(($filters['kind'] ?? '') === 'individual')>Individual donations</option><option value="mass_collection" @selected(($filters['kind'] ?? '') === 'mass_collection')>Mass collections</option></select></x-admin.field>
                        <x-admin.field label="Church / chapel" name="church"><select class="admin-input" name="church"><option value="">All churches</option>@foreach(config('parish.churches') as $church)<option value="{{ $church }}" @selected(($filters['church'] ?? '') === $church)>{{ $church }}</option>@endforeach</select></x-admin.field>
                        <x-admin.field label="Collection type" name="collection_type"><select class="admin-input" name="collection_type"><option value="">All collections</option>@foreach(\App\Models\Donation::COLLECTION_TYPES as $key => $label)<option value="{{ $key }}" @selected(($filters['collection_type'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></x-admin.field>
                    </div>
                </fieldset>
            </div>
        </details>
        <div class="flex flex-col gap-4 border-t border-navy/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs leading-5 text-muted">Leave dates blank to include all dates. Filters apply to records and summaries.</p>
            <div class="grid grid-cols-2 gap-2 sm:flex sm:shrink-0">
                <a class="admin-secondary" href="{{ route('admin.donations.index') }}">Reset</a>
                <button type="submit" class="admin-button">Apply filters</button>
            </div>
        </div>
    </form>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <section class="admin-card"><h2 class="font-sans text-sm text-muted">Donations received</h2><p class="mt-3 text-2xl font-semibold tabular-nums">₱{{ number_format($totals->received_cents / 100, 2) }}</p></section>
        <section class="admin-card"><h2 class="font-sans text-sm text-muted">Number of donations and collections</h2><p class="mt-3 text-2xl font-semibold">{{ number_format($totals->received_count) }}</p></section>
        <section class="admin-card"><h2 class="font-sans text-sm text-muted">Mass collections</h2><p class="mt-3 text-2xl font-semibold">{{ number_format($totals->mass_count) }}</p></section>
        <section class="admin-card"><h2 class="font-sans text-sm text-muted">Canceled records</h2><p class="mt-3 text-2xl font-semibold">{{ number_format($totals->voided_count) }}</p></section>
    </div>
    <p class="text-sm leading-6 text-muted">Each donation and Mass collection is counted once. Donor receipts already included in Mass totals: ₱{{ number_format($totals->included_cents / 100, 2) }} (not added again). Totals include all matching pages. Canceled records are not counted. Changes affect totals only after approval.</p>
    <section class="admin-card">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-2xl">Donation records <span class="text-base text-muted">({{ $donations->total() }})</span></h2>@can('reports.view')<a class="admin-secondary" href="{{ route('admin.donations.index', [...$filters, 'export' => 'records']) }}">Download donation report (CSV)</a>@endcan</div>
        <div class="mt-5 overflow-x-auto"><table class="admin-table"><thead><tr><th>Date / receipt</th><th>Donor</th><th>Category</th><th>Amount (PHP)</th><th>Method / reference</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            @forelse($donations as $donation)
                <tr><td>{{ $donation->donated_on->format('M j, Y') }}<br><a class="break-all underline" href="{{ route('admin.donations.show', $donation) }}">{{ $donation->receipt_number }}</a></td><td>{{ $donation->donorLabel() }}@if($donation->massCollection)<br><span class="text-xs text-muted">Included in <a class="underline" href="{{ route('admin.donations.show', $donation->massCollection) }}">{{ $donation->massCollection->receipt_number }}</a></span>@endif @if($donation->isMassCollection())<br><span class="text-xs text-muted">{{ $donation->church }} &middot; {{ $donation->mass_time }}</span>@endif</td><td>{{ \App\Models\Donation::CATEGORIES[$donation->category] }}</td><td class="whitespace-nowrap tabular-nums">₱{{ number_format($donation->amount_cents / 100, 2) }}</td><td>{{ \App\Models\Donation::METHODS[$donation->method] }}<br>{{ $donation->reference }}</td><td>{{ $donation->status === 'voided' ? 'Canceled' : ucfirst($donation->status) }}@if($donation->pending_count)<br><span class="text-amber-800">Awaiting approval</span>@endif</td><td><a class="underline" href="{{ route('admin.donations.show', $donation) }}">Manage<span class="sr-only"> {{ $donation->receipt_number }}</span></a><br><a class="underline" href="{{ route('admin.donations.receipt', $donation) }}" target="_blank" rel="noopener">Receipt<span class="sr-only"> {{ $donation->receipt_number }} (new tab)</span></a></td></tr>
            @empty<tr><td colspan="7" class="text-muted">No donations match these filters.</td></tr>@endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $donations->links() }}</div>
    </section>
    <div class="grid gap-6 xl:grid-cols-2">
        @foreach(['category' => ['title' => 'By category', 'rows' => $byCategory, 'labels' => \App\Models\Donation::CATEGORIES], 'method' => ['title' => 'By payment method', 'rows' => $byMethod, 'labels' => \App\Models\Donation::METHODS]] as $group => $report)
            <section class="admin-card"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-2xl">{{ $report['title'] }}</h2>@can('reports.view')<a class="underline text-sm" href="{{ route('admin.donations.index', [...$filters, 'export' => $group]) }}">Download report (CSV)</a>@endcan</div>
                <div class="mt-4 overflow-x-auto"><table class="admin-table"><thead><tr><th>{{ ucfirst($group) }}</th><th>Donations</th><th>Received (PHP)</th></tr></thead><tbody>@forelse($report['rows'] as $row)<tr><td>{{ $report['labels'][$row->$group] }}</td><td>{{ $row->entries }}</td><td class="whitespace-nowrap tabular-nums">₱{{ number_format($row->total_cents / 100, 2) }}</td></tr>@empty<tr><td colspan="3">No recorded donations.</td></tr>@endforelse</tbody></table></div>
            </section>
        @endforeach
    </div>
    <section class="admin-card"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-2xl">Monthly donations</h2>@can('reports.view')<a class="admin-secondary" href="{{ route('admin.donations.index', [...$filters, 'export' => 'monthly']) }}">Download monthly report (CSV)</a>@endcan</div>
        <div class="mt-4 max-h-96 overflow-auto"><table class="admin-table"><thead><tr><th>Month</th><th>Donations</th><th>Received (PHP)</th></tr></thead><tbody>@forelse($monthly as $row)<tr><td>{{ $row->month }}</td><td>{{ $row->entries }}</td><td class="tabular-nums">₱{{ number_format($row->total_cents / 100, 2) }}</td></tr>@empty<tr><td colspan="3">No recorded donations.</td></tr>@endforelse</tbody></table></div>
    </section>
    <section class="admin-card">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-2xl">Mass collections by church</h2>@can('reports.view')<a class="admin-secondary" href="{{ route('admin.donations.index', [...$filters, 'export' => 'church']) }}">Download church report (CSV)</a>@endcan</div>
        <div class="mt-4 overflow-x-auto"><table class="admin-table"><thead><tr><th>Church / chapel</th><th>Collections</th><th>Received (PHP)</th></tr></thead><tbody>@forelse($byChurch as $row)<tr><td>{{ $row->church }}</td><td>{{ $row->entries }}</td><td>₱{{ number_format($row->total_cents / 100, 2) }}</td></tr>@empty<tr><td colspan="3">No Mass collections match these filters.</td></tr>@endforelse</tbody></table></div>
    </section>
    <section class="admin-card">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-2xl">Collections by Mass</h2>@can('reports.view')<a class="admin-secondary" href="{{ route('admin.donations.index', [...$filters, 'export' => 'masses']) }}">Download Mass collection report (CSV)</a>@endcan</div>
        <p class="mt-2 text-sm text-muted">Total collected at each Mass, including donations with individual receipts.</p>
        <div class="mt-4 overflow-x-auto"><table class="admin-table"><thead><tr><th>Mass date / time</th><th>Church</th><th>Collection</th><th>Method</th><th>Received (PHP)</th></tr></thead><tbody>@forelse($masses as $mass)<tr><td><a class="underline" href="{{ route('admin.donations.show', $mass) }}">{{ $mass->donated_on->format('M j, Y') }} · {{ $mass->mass_time }}</a></td><td>{{ $mass->church }}</td><td>{{ \App\Models\Donation::COLLECTION_TYPES[$mass->collection_type] }}@if($mass->collection_purpose)<br>{{ $mass->collection_purpose }}@endif</td><td>{{ \App\Models\Donation::METHODS[$mass->method] }}</td><td>₱{{ number_format($mass->amount_cents / 100, 2) }}</td></tr>@empty<tr><td colspan="5">No Mass collections match these filters.</td></tr>@endforelse</tbody></table></div>
        <div class="mt-4">{{ $masses->links() }}</div>
    </section>
@endsection
