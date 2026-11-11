@php
    $sourceLabels = ['services' => 'Service payments after refunds', 'donations' => 'Individual donations', 'masses' => 'Mass collections'];
    $sourceMaximum = max(1, ...array_map('abs', array_values($overview['sources'])));
    $monthlyMaximum = max([1, ...array_map(fn (array $row): int => abs($row['total']), $overview['monthly'])]);
@endphp
<p class="text-sm leading-6 text-muted">Dates and payment method apply to all totals. Service filters apply only to appointment payments. Mass donations are counted once. Canceled records and unpaid fees are not included. Test payments are included and shown below.</p>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach($sourceLabels as $key => $label)
        <section class="admin-card"><h2 class="font-sans text-sm font-medium text-muted">{{ $label }}</h2><p class="mt-3 text-2xl font-semibold tabular-nums">₱{{ number_format($overview['sources'][$key] / 100, 2) }}</p></section>
    @endforeach
    <section class="admin-card border-navy/30 bg-ivory"><h2 class="font-sans text-sm font-semibold">Total parish revenue</h2><p class="mt-3 text-3xl font-semibold tabular-nums">₱{{ number_format($overview['combined'] / 100, 2) }}</p><p class="mt-2 text-xs text-muted">Service payments after refunds + donations + Mass collections</p></section>
</div>
<div class="grid gap-6 xl:grid-cols-2">
    <section class="admin-card">
        <h2 class="text-2xl">Revenue by source</h2>
        <p class="mt-2 text-sm text-muted">Amounts are in pesos. A bar left of zero means refunds were higher than payments received.</p>
        <figure class="mt-5" aria-label="Revenue by source in Philippine pesos">
            @foreach($sourceLabels as $key => $label)
                @php($value = $overview['sources'][$key])
                <div class="mt-4"><div class="flex flex-wrap justify-between gap-2 text-sm"><span>{{ $label }}</span><strong class="tabular-nums">₱{{ number_format($value / 100, 2) }}</strong></div>
                    <svg viewBox="0 0 500 32" class="mt-2 h-8 w-full" role="img" aria-label="{{ $label }}: PHP {{ number_format($value / 100, 2) }}">
                        <line x1="250" y1="0" x2="250" y2="32" stroke="#64748b" />
                        <rect x="{{ $value < 0 ? 250 - abs($value) / $sourceMaximum * 240 : 250 }}" y="5" width="{{ abs($value) / $sourceMaximum * 240 }}" height="22" rx="4" fill="{{ $value < 0 ? '#b91c1c' : match ($key) { 'services' => '#13233f', 'donations' => '#047857', default => '#967020' } }}"><title>{{ $label }}: PHP {{ number_format($value / 100, 2) }}</title></rect>
                    </svg>
                </div>
            @endforeach
            <figcaption class="mt-3 text-xs text-muted">Center line = ₱0. Each bar uses the same scale.</figcaption>
        </figure>
    </section>
    <section class="admin-card">
        <h2 class="text-2xl">Monthly revenue trend</h2>
        <p class="mt-2 text-sm text-muted">Monthly totals for the selected dates. Months with no records are not shown.</p>
        @if(count($overview['monthly']))
            <div class="mt-5 overflow-x-auto" tabindex="0" role="region" aria-label="Monthly revenue chart; scroll horizontally to see all months">
                <svg width="{{ max(420, count($overview['monthly']) * 85) }}" height="280" role="img" aria-label="Monthly combined revenue in PHP. Exact source amounts are in the table below.">
                    <line x1="0" y1="130" x2="{{ max(420, count($overview['monthly']) * 85) }}" y2="130" stroke="#64748b" />
                    @foreach($overview['monthly'] as $row)
                        @php($height = abs($row['total']) / $monthlyMaximum * 95)
                        <rect x="{{ $loop->index * 85 + 15 }}" y="{{ $row['total'] < 0 ? 130 : 130 - $height }}" width="50" height="{{ $height }}" rx="3" fill="{{ $row['total'] < 0 ? '#b91c1c' : '#13233f' }}"><title>{{ $row['month'] }}: PHP {{ number_format($row['total'] / 100, 2) }}</title></rect>
                        <text x="{{ $loop->index * 85 + 40 }}" y="{{ $row['total'] < 0 ? 145 + $height : 120 - $height }}" text-anchor="middle" font-size="10" fill="currentColor">{{ number_format($row['total'] / 100, 2) }}</text>
                        <text x="{{ $loop->index * 85 + 40 }}" y="265" text-anchor="middle" font-size="11" fill="currentColor">{{ $row['month'] }}</text>
                    @endforeach
                </svg>
            </div>
        @else
            <p class="mt-6 rounded-lg bg-slate-50 p-5 text-sm text-muted">No revenue activity matches these dates and payment method.</p>
        @endif
    </section>
</div>
<section class="admin-card">
    <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-2xl">Monthly revenue details</h2>@can('reports.view')<a class="admin-secondary" href="{{ route('admin.revenue.index', [...$filters, 'export' => 'revenue']) }}">Download revenue report (CSV)</a>@endcan</div>
    <div class="mt-5 overflow-x-auto"><table class="admin-table"><caption class="sr-only">Monthly amounts in PHP matching the revenue charts</caption><thead><tr><th scope="col">Month</th><th scope="col">Services after refunds</th><th scope="col">Individual donations</th><th scope="col">Mass collections</th><th scope="col">Total revenue</th></tr></thead><tbody>
        @forelse($overview['monthly'] as $row)<tr><th scope="row">{{ $row['month'] }}</th>@foreach(['services', 'donations', 'masses', 'total'] as $key)<td class="whitespace-nowrap tabular-nums">₱{{ number_format($row[$key] / 100, 2) }}</td>@endforeach</tr>@empty<tr><td colspan="5">No revenue activity matches these filters.</td></tr>@endforelse
    </tbody><tfoot><tr><th scope="row">Total</th>@foreach($overview['sources'] as $cents)<td class="whitespace-nowrap font-semibold">₱{{ number_format($cents / 100, 2) }}</td>@endforeach<td class="whitespace-nowrap font-semibold">₱{{ number_format($overview['combined'] / 100, 2) }}</td></tr></tfoot></table></div>
    <p class="mt-3 text-xs text-muted">Donation details are available to authorized users on the Donations page. The payment records below cover appointments only.</p>
</section>
