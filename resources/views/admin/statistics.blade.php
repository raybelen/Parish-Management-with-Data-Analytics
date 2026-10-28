@extends('admin.layout')
@section('title', 'Parish statistics')
@section('actions')
    @can('forecasts.manage')<a class="admin-secondary" href="{{ route('admin.forecasts.index', ['service' => in_array($service, \App\Support\ParishStatistics::FORECAST_SERVICES, true) ? $service : 'baptism']) }}">View forecasts</a>@endcan
@endsection
@section('content')
    @php
        $serviceName = \Illuminate\Support\Str::before($services[$service], ' (');
        $monthlyService = in_array($service, \App\Support\ParishStatistics::FORECAST_SERVICES, true);
        $periodChange = $monthlyService ? ($insights['comparison']['percent'] ?? null) : $summary['change'];
    @endphp
    <p class="text-sm text-muted">See parish activity at a glance and explore how it changes over time.</p>
    <form class="admin-card grid items-end gap-4 sm:grid-cols-[minmax(0,1fr)_8rem_auto] xl:grid-cols-[minmax(0,1fr)_8rem_auto_auto]" method="GET">
        <x-admin.field label="Service" name="service"><select class="admin-input" name="service">@foreach($services as $code => $label)<option value="{{ $code }}" @selected($service === $code)>{{ $label }}</option>@endforeach</select></x-admin.field>
        <x-admin.field label="Year" name="year"><input class="admin-input" type="number" name="year" min="1900" max="{{ now()->year }}" value="{{ $year }}" required></x-admin.field>
        <button class="admin-button">View statistics</button>
        <a class="admin-secondary" href="{{ route('admin.statistics.index', ['year' => $year, 'service' => $service, 'export' => 1]) }}">Download totals</a>
    </form>

    <section aria-label="Year at a glance" @class(['grid gap-4 sm:grid-cols-2', 'xl:grid-cols-4' => $monthlyService, 'lg:grid-cols-3' => !$monthlyService])>
        <div class="admin-card">
            <p class="text-sm text-muted">{{ $serviceName }} in {{ $year }}</p>
            <p class="mt-3 text-3xl font-semibold tabular-nums">{{ $summary['total'] === null ? '—' : number_format($summary['total']) }}</p>
            <p class="mt-2 text-xs text-muted">{{ $summary['months'] === 12 ? 'Full-year total' : ($summary['months'] > 0 ? 'Partial total · verified months only' : 'No verified totals yet') }}</p>
        </div>
        <div class="admin-card">
            <p class="text-sm text-muted">Compared with {{ $year - 1 }}</p>
            <p class="mt-3 text-3xl font-semibold tabular-nums">{{ $periodChange === null ? '—' : number_format(abs($periodChange), 1).'%' }}</p>
            <p class="mt-2 text-xs text-muted">{{ $periodChange === null ? ($monthlyService ? 'Needs matching verified months; a percentage requires the earlier total is above zero' : 'Available when both years are complete and the earlier total is above zero') : ($periodChange > 0 ? 'More than the previous year' : ($periodChange < 0 ? 'Fewer than the previous year' : 'Same as the previous year')) }}</p>
            @if($monthlyService && $insights['comparison'])
                <p class="mt-2 text-xs text-muted">{{ $insights['period'] }} in both years: {{ number_format($insights['comparison']['current']) }} vs {{ number_format($insights['comparison']['previous']) }}.</p>
            @endif
        </div>
        @if($monthlyService)
            <div class="admin-card">
                <p class="text-sm text-muted">Average per recorded month</p>
                <p class="mt-3 text-3xl font-semibold tabular-nums">{{ $insights['average'] === null ? '—' : number_format($insights['average'], 1) }}</p>
                <p class="mt-2 text-xs text-muted">{{ $insights['verified'] }} verified months, including {{ $insights['zero_months'] }} with no services.</p>
            </div>
        @endif
        <div class="admin-card">
            <p class="text-sm text-muted">Record completeness</p>
            <p class="mt-3 text-3xl font-semibold tabular-nums">{{ $insights['verified'] }} <span class="text-base font-normal text-muted">of {{ $insights['expected'] }} completed months</span></p>
            <p class="mt-2 text-xs text-muted">{{ $insights['expected'] === 0 ? 'No months have finished yet' : ($insights['missing'] === [] ? 'All completed months verified' : count($insights['missing']).' months need verification') }}. {{ $summary['months'] }}/12 months in the annual total.</p>
            @can('records.manage')
                @if($insights['missing'] !== [])<a class="mt-3 inline-flex text-sm font-semibold underline underline-offset-4" href="#monthly-records">Review missing months</a>@endif
            @endcan
        </div>
    </section>

    @if($monthlyService)
        <section class="admin-card">
            <h2 class="text-2xl">Monthly activity</h2>
            <p class="mt-2 text-sm text-muted">{{ $services[$service] }} · {{ $year }}</p>
            @include('admin.partials.statistics-chart', ['points' => $monthlyChart, 'chartLabel' => $serviceName.' by month in '.$year, 'comparisonYear' => $year - 1])
            <details class="mt-5 border-t border-navy/10 pt-4">
                <summary class="cursor-pointer text-sm font-semibold">View monthly comparison table</summary>
                <div class="mt-3 overflow-x-auto"><table class="admin-table"><caption class="sr-only">Monthly {{ $serviceName }} totals for {{ $year }} and {{ $year - 1 }}</caption><thead><tr><th>Month</th><th>{{ $year }}</th><th>{{ $year - 1 }}</th></tr></thead><tbody>
                    @foreach($monthlyChart as $point)<tr><td>{{ $point['label'] }}</td><td>{{ $point['value'] === null ? 'Not available' : number_format($point['value']) }}</td><td>{{ $point['previous'] === null ? 'Not available' : number_format($point['previous']) }}</td></tr>@endforeach
                </tbody></table></div>
            </details>
        </section>
        <details class="admin-card">
            <summary class="cursor-pointer font-semibold">Compare across the years</summary>
            <div class="mt-4">@include('admin.partials.statistics-chart', ['points' => $annualChart, 'chartLabel' => $serviceName.' by year'])</div>
        </details>
    @else
        <section class="admin-card">
            <h2 class="text-2xl">{{ $serviceName }} by year</h2>
            <p class="mt-2 text-sm text-muted">Annual totals for group ceremonies.</p>
            @include('admin.partials.statistics-chart', ['points' => $annualChart, 'chartLabel' => $serviceName.' by year'])
        </section>
    @endif

    @include('admin.partials.statistics-insights')

    <details id="monthly-records" class="admin-card scroll-mt-24" @if($errors->any()) open @endif>
        <summary class="cursor-pointer font-semibold">Monthly records · {{ $year }}</summary>
        <p class="mt-3 text-sm text-muted">Review the counts behind this summary. Only verified totals are included in the charts.</p>
        <div class="mt-4 overflow-x-auto"><table class="admin-table"><thead><tr><th>Month</th><th>Total</th><th>Status</th><th>Review</th></tr></thead><tbody>
        @for($number = 1; $number <= 12; $number++)
            @php
                $month = sprintf('%04d-%02d', $year, $number);
                $row = $rows->get($month);
                $closed = $month < now()->format('Y-m');
                $retry = old('month') === $month && old('service_type') === $service;
            @endphp
            <tr>
                <td>{{ \Carbon\CarbonImmutable::parse($month.'-01')->format('F') }}</td>
                <td>{{ $row?->count === null ? 'Unknown' : number_format($row->count) }}</td>
                <td>{{ $row ? ($row->status === 'confirmed' ? 'Verified' : 'Needs review') : ($closed ? 'Not recorded' : 'Month not finished') }}</td>
                <td>
                    @can('records.manage')
                    @if($closed && ($row?->status !== 'confirmed' || auth()->user()->can('forecasts.manage')))
                    <details @if($retry) open @endif><summary class="cursor-pointer text-link">Review {{ \Carbon\CarbonImmutable::parse($month.'-01')->format('F') }}</summary>
                        <form method="POST" action="{{ route('admin.statistics.store') }}" class="mt-4 grid min-w-64 gap-3">
                            @csrf
                            <input type="hidden" name="month" value="{{ $month }}">
                            <input type="hidden" name="service_type" value="{{ $service }}">
                            <input type="hidden" name="version" value="{{ $retry ? old('version') : ($row?->version ?? 0) }}">
                            <x-admin.field label="Total for the whole month" name="count"><input class="admin-input" name="count" type="number" min="0" max="1000000" value="{{ $retry ? old('count') : ($row?->count ?? ($suggestions[$month] ?? '')) }}"></x-admin.field>
                            <x-admin.field label="Register / report reference" name="reference" required><input class="admin-input" name="reference" maxlength="255" required value="{{ $retry ? old('reference') : $row?->reference }}"></x-admin.field>
                            <x-admin.field label="Notes" name="notes"><textarea class="admin-input" name="notes" rows="2" maxlength="2000">{{ $retry ? old('notes') : $row?->notes }}</textarea></x-admin.field>
                            <x-admin.field label="Reason for entry or correction" name="reason" required><input class="admin-input" name="reason" maxlength="1000" required value="{{ $retry ? old('reason') : '' }}"></x-admin.field>
                            <p class="text-xs text-muted">Check the parish register before confirming. Enter 0 only if no services took place; leave unknown counts blank.</p>
                            @if(isset($suggestions[$month]))<p class="text-xs text-muted">Digital records suggest {{ number_format($suggestions[$month]) }}. Check group services and walk-ins before using this count.</p>@endif
                            <div class="flex flex-wrap gap-2"><button class="admin-secondary" name="status" value="draft">Save draft</button>@can('forecasts.manage')<button class="admin-button" name="status" value="confirmed">Confirm complete total</button>@endcan</div>
                        </form>
                    </details>
                    @else <span class="text-sm text-muted">{{ $closed ? 'Confirmation permission required' : 'Available after month ends' }}</span> @endif
                    @endcan
                </td>
            </tr>
        @endfor

        </tbody></table></div>
    </details>

    <details class="admin-card">
        <summary class="cursor-pointer font-semibold">All services by year</summary>
        <p class="my-4 text-sm text-muted">Annual historical analytics. Partial years include verified months only.</p>
        <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Year</th>@foreach($services as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>
        @forelse($annual as $annualRow)
            <tr><td>{{ $annualRow['year'] }}</td>@foreach($services as $code => $label)@php($cell = $annualRow['services'][$code])<td>{{ $cell['total'] === null ? 'Unavailable' : number_format($cell['total']) }}<span class="block text-xs text-muted">{{ $cell['months'] }}/12 confirmed months{{ $cell['months'] > 0 && $cell['months'] < 12 ? ' — partial total' : '' }}</span></td>@endforeach</tr>
        @empty<tr><td colspan="6">No confirmed monthly statistics yet.</td></tr>@endforelse
        </tbody></table></div>

    </details>

    <details class="admin-card">
        <summary class="cursor-pointer text-sm font-semibold">Record history and import</summary>
        <div class="mt-4 grid gap-4">
    @can('forecasts.manage')@can('records.manage')
    <details class="rounded-xl border border-navy/10 p-4"><summary class="cursor-pointer font-semibold">Import historical reports</summary>
        <p class="my-4 text-sm text-muted">Imports the accepted monthly sums from the supplied parish reports. Existing matching totals are skipped; conflicting edits stop the entire import. First Communicants in 2015–2017 remain unavailable.</p>
        <form method="POST" action="{{ route('admin.statistics.import') }}">@csrf<button class="admin-secondary">Import verified history</button></form>
    </details>
    @endcan @endcan
    <details class="rounded-xl border border-navy/10 p-4"><summary class="cursor-pointer font-semibold">Changes to these records</summary>
        @forelse($revisions as $revision)<p class="mt-3 text-sm">{{ $revision->created_at->format('M j, Y H:i') }} — {{ $revision->actor?->name ?? 'System' }} — {{ $revision->reason }} — {{ $revision->before['count'] ?? 'Unknown' }} → {{ $revision->after['count'] ?? 'Unknown' }} ({{ $revision->after['status'] ?? '' }})</p>@empty<p class="mt-3 text-sm text-muted">No manual revisions.</p>@endforelse
    </details>

        </div>
    </details>
@endsection
