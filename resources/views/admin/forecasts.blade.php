@extends('admin.layout')
@section('title', 'Service Analytics')
@section('actions')
    @can('reports.view')
        <a class="admin-secondary" href="{{ route('admin.statistics.index', ['service' => $service]) }}">View recorded totals</a>
    @endcan
@endsection
@section('content')
    @php
        $serviceName = \Illuminate\Support\Str::before($services[$service], ' (');
        $unit = ['baptism' => 'people', 'wedding' => 'couples', 'funeral' => 'Funeral Masses'][$service];
        $cutoffDate = $cutoff ? \Carbon\CarbonImmutable::parse($cutoff.'-01') : null;
        $hasOutlook = ! $stale && ! empty($run?->results['count']['forecast']);
        $historical = $cutoff && $cutoff < $latestCompleteMonth;
        $reviewUrl = route('admin.statistics.index', ['service' => $service, 'year' => $cutoffDate?->addMonth()->year ?? now()->year]).'#monthly-records';
    @endphp

    <section class="overflow-hidden rounded-2xl border border-navy/10 bg-navy text-white shadow-[0_16px_40px_rgb(19_35_63/0.12)]">
        <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold tracking-[0.18em] text-gold uppercase">Planning overview</p>
                <h2 class="mt-3 text-3xl leading-tight text-white sm:text-4xl">Plan ahead with a clearer view of parish activity.</h2>
                <p class="mt-3 max-w-xl text-sm leading-6 text-white/75 sm:text-base">See the next six months at a glance, understand the likely range, and check how much confidence to place in the estimates.</p>
            </div>
            <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/8 px-4 py-3 text-sm">
                <x-parish.icon name="calendar" class="size-5 shrink-0 text-gold" />
                <span><strong class="block text-white">{{ $serviceName }}</strong><span class="text-white/65">{{ $cutoffDate ? 'Records through '.$cutoffDate->format('F Y') : 'No recorded totals yet' }}</span></span>
            </div>
        </div>
    </section>

    <section class="admin-card" aria-labelledby="service-filter-heading">
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
            <div>
                <h2 id="service-filter-heading" class="text-xl">Choose a service</h2>
                <p class="mt-1 text-sm text-muted">Switch between services to see the information that matters for your planning.</p>
                <nav class="mt-4 grid gap-2 sm:grid-cols-3" aria-label="Filter estimates by service">
                    @foreach($services as $code => $label)
                        <a href="{{ route('admin.forecasts.index', ['service' => $code]) }}" @class([
                            'flex min-h-11 items-center justify-between gap-3 rounded-xl border px-4 py-3 text-sm font-semibold transition',
                            'border-navy bg-navy text-white shadow-sm' => $service === $code,
                            'border-navy/10 bg-slate-50 text-navy hover:border-gold/50 hover:bg-ivory/60' => $service !== $code,
                        ]) @if($service === $code) aria-current="page" @endif>
                            <span>{{ \Illuminate\Support\Str::before($label, ' (') }}</span>
                            @if($service === $code)<span class="text-xs font-medium text-white/65">Selected</span>@endif
                        </a>
                    @endforeach
                </nav>
            </div>
            <form method="POST" action="{{ route('admin.forecasts.store') }}" data-forecast-form class="lg:text-right">
                @csrf
                <input type="hidden" name="service" value="{{ $service }}">
                <button class="admin-button w-full lg:w-auto">{{ $run ? 'Refresh analytics' : 'Prepare estimates' }}</button>
                <p class="mt-2 hidden text-xs text-muted" role="status" data-forecast-progress>Preparing the latest estimates. This may take a moment.</p>
            </form>
        </div>
    </section>

    @if($historical)
        <section class="admin-alert border-amber-200 bg-amber-50 text-amber-950" aria-labelledby="historical-outlook-heading">
            <div class="flex gap-3">
                <x-parish.icon name="clock" class="mt-0.5 size-5 shrink-0" />
                <div>
                    <h2 id="historical-outlook-heading" class="font-sans text-sm font-semibold">Historical estimates</h2>
                    <p class="mt-1 text-sm">These estimates start after {{ $cutoffDate->format('F Y') }}. Add recent monthly totals before using them for current planning.</p>
                    @can('reports.view')<a class="mt-2 inline-flex text-sm font-semibold underline underline-offset-4" href="{{ $reviewUrl }}">Review recent records</a>@endcan
                </div>
            </div>
        </section>
    @endif

    @if($stale)
        <section class="admin-alert border-amber-200 bg-amber-50 text-amber-950">
            <p class="font-semibold">New estimates are needed</p>
            <p class="text-sm">The saved estimates are outdated because recorded totals have changed. Refresh them to see the latest information.</p>
        </section>
    @elseif($hasOutlook && $run->status === 'unreliable')
        <section class="admin-alert border-amber-200 bg-amber-50 text-amber-950">
            <p class="font-semibold">Plan with extra flexibility</p>
            <p class="text-sm">Past estimates for this service were less dependable than a simple comparison. Use the possible range and leave room for change.</p>
        </section>
    @elseif(! $hasOutlook)
        <section class="admin-card text-center">
            <span class="mx-auto grid size-12 place-items-center rounded-full bg-ivory text-navy"><x-parish.icon name="chart" class="size-6" /></span>
            <h2 class="mt-4 text-2xl">{{ $run?->status === 'unavailable' ? 'The estimates are temporarily unavailable' : 'No estimates to show yet' }}</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-muted">{{ $run?->status === 'unavailable' ? 'Try refreshing them again. Contact your system administrator if the issue continues.' : ($run ? 'More complete monthly records are needed before estimates can be prepared.' : 'Prepare estimates once the monthly records are complete.') }}</p>
            @can('reports.view')<a class="admin-secondary mt-4" href="{{ $reviewUrl }}">Review monthly records</a>@endcan
        </section>
    @endif

    @if($hasOutlook)
        @php
            $points = $run->results['count']['forecast'];
            $evaluation = $run->results['count']['evaluation'];
            $firstMonth = \Carbon\CarbonImmutable::parse(collect($run->history)->last()['month'].'-01')->addMonth();
            $peakIndex = array_search(max(array_column($points, 'estimate')), array_column($points, 'estimate'));
            $averageEstimate = collect($points)->average('estimate');
        @endphp

        <section aria-labelledby="outlook-summary-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><p class="text-xs font-semibold tracking-[0.16em] text-gold uppercase">At a glance</p><h2 id="outlook-summary-heading" class="mt-1 text-2xl sm:text-3xl">What to expect</h2></div>
                <p class="text-sm text-muted">Updated {{ $run->created_at->format('M j, Y') }}</p>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <article class="admin-card relative overflow-hidden"><span class="absolute top-0 right-0 size-24 translate-x-8 -translate-y-8 rounded-full bg-gold/10" aria-hidden="true"></span><p class="text-sm font-medium text-muted">{{ $firstMonth->format('F Y') }}</p><p class="mt-3 text-4xl font-semibold tabular-nums">{{ number_format(round($points[0]['estimate'])) }}</p><p class="mt-2 text-sm text-muted">Expected {{ $unit }} for the first month</p></article>
                <article class="admin-card relative overflow-hidden"><span class="absolute top-0 right-0 size-24 translate-x-8 -translate-y-8 rounded-full bg-navy/5" aria-hidden="true"></span><p class="text-sm font-medium text-muted">Busiest expected month</p><p class="mt-3 text-4xl font-semibold">{{ $firstMonth->addMonths($peakIndex)->format('F') }}</p><p class="mt-2 text-sm text-muted">About {{ number_format(round($points[$peakIndex]['estimate'])) }} {{ $unit }}</p></article>
                <article class="admin-card relative overflow-hidden"><span class="absolute top-0 right-0 size-24 translate-x-8 -translate-y-8 rounded-full bg-emerald-100/60" aria-hidden="true"></span><p class="text-sm font-medium text-muted">Monthly planning guide</p><p class="mt-3 text-4xl font-semibold tabular-nums">{{ number_format(round($averageEstimate)) }}</p><p class="mt-2 text-sm text-muted">Average expected {{ $unit }} per month</p></article>
            </div>
        </section>

        <section class="admin-card" aria-labelledby="activity-outlook-heading">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><p class="text-xs font-semibold tracking-[0.16em] text-gold uppercase">Six-month view</p><h2 id="activity-outlook-heading" class="mt-1 text-2xl sm:text-3xl">Recorded activity and estimated demand</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-muted">The dots mark each month. Navy shows recorded totals, while gold shows the next six estimated months.</p></div>
                <span @class(['inline-flex rounded-full px-3 py-1.5 text-xs font-semibold', 'bg-emerald-50 text-emerald-800' => $run->status === 'ready', 'bg-amber-50 text-amber-900' => $run->status !== 'ready'])>{{ $run->status === 'ready' ? 'Useful planning guide' : 'Use with extra care' }}</span>
            </div>
            @include('admin.partials.forecast-chart', ['run' => $run, 'metric' => 'count', 'label' => $services[$service]])
            <div class="hidden overflow-hidden rounded-xl border border-navy/10 sm:block">
                <table class="admin-table"><thead><tr><th>Month</th><th>Expected {{ $unit }}</th><th>Possible range</th></tr></thead><tbody>
                    @foreach($points as $index => $point)<tr><td class="font-medium">{{ $firstMonth->addMonths($index)->format('F Y') }}</td><td class="font-semibold tabular-nums">{{ number_format(round($point['estimate'])) }}</td><td class="tabular-nums">{{ number_format(floor($point['lower'])) }} – {{ number_format(ceil($point['upper'])) }}</td></tr>@endforeach
                </tbody></table>
            </div>
            <div class="grid gap-3 sm:hidden" aria-label="Monthly estimates">
                @foreach($points as $index => $point)
                    <article class="rounded-xl border border-navy/10 bg-slate-50 p-4"><p class="font-semibold">{{ $firstMonth->addMonths($index)->format('F Y') }}</p><dl class="mt-3 grid grid-cols-2 gap-4 text-sm"><div><dt class="text-xs text-muted">Expected</dt><dd class="mt-1 font-semibold tabular-nums">{{ number_format(round($point['estimate'])) }} {{ $unit }}</dd></div><div><dt class="text-xs text-muted">Possible range</dt><dd class="mt-1 font-semibold tabular-nums">{{ number_format(floor($point['lower'])) }}–{{ number_format(ceil($point['upper'])) }}</dd></div></dl></article>
                @endforeach
            </div>
            <p class="mt-4 text-xs leading-5 text-muted">Use the possible range when arranging people, spaces, and supplies. Actual totals may still fall outside it.</p>
        </section>

        <details class="admin-card group" id="forecast-details">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-base font-semibold marker:hidden"><span>How dependable are these estimates?</span><x-parish.icon name="chevron-down" class="size-5 shrink-0 transition-transform group-open:rotate-180" /></summary>
            <div class="mt-5 grid gap-5 border-t border-navy/10 pt-5">
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_16rem] lg:items-start">
                    <div><h3 class="text-xl">Checked against past records</h3><p class="mt-2 text-sm leading-6 text-muted">These estimates were tested using actual totals from {{ \Carbon\CarbonImmutable::parse($evaluation['test_start'].'-01')->format('F Y') }} to {{ \Carbon\CarbonImmutable::parse($evaluation['test_end'].'-01')->format('F Y') }}. A smaller difference means the past estimate was closer to what actually happened.</p></div>
                    <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold tracking-[0.12em] text-muted uppercase">Typical past difference</p><p class="mt-2 text-3xl font-semibold tabular-nums">{{ number_format($evaluation['mae'], 1) }}</p><p class="mt-1 text-xs text-muted">{{ $unit }} per month</p></div>
                </div>
                <div class="overflow-hidden rounded-xl border border-navy/10"><table class="admin-table"><thead><tr><th>Way of estimating</th><th>Typical difference</th></tr></thead><tbody>
                    @foreach(['mae' => 'These estimates', 'seasonal_baseline_mae' => 'Using the same month last year', 'recent_mean_mae' => 'Using the recent monthly average'] as $key => $comparisonLabel)<tr><td>{{ $comparisonLabel }}</td><td class="font-semibold tabular-nums">{{ number_format($evaluation[$key], 2) }} {{ $unit }}</td></tr>@endforeach
                </tbody></table></div>
                @if(! empty($evaluation['by_horizon']))
                    <details class="rounded-xl border border-navy/10 p-4"><summary class="cursor-pointer text-sm font-semibold">See the month-by-month reliability check</summary><div class="mt-4 overflow-x-auto"><table class="admin-table"><thead><tr><th>How far ahead</th><th>Typical difference</th><th>Inside the possible range</th></tr></thead><tbody>
                        @foreach($evaluation['by_horizon'] as $row)<tr><td>{{ $row['horizon'] }} {{ \Illuminate\Support\Str::plural('month', $row['horizon']) }}</td><td>{{ number_format($row['mae'], 2) }} {{ $unit }}</td><td>{{ $row['covered'] }} of {{ $row['total'] }} checks</td></tr>@endforeach
                    </tbody></table></div></details>
                @endif
                <p class="text-xs leading-5 text-muted">These checks are a planning guide, not a guarantee. The same set of past months is checked at several points in time.</p>
            </div>
        </details>
    @endif

    <details class="admin-card group">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-semibold marker:hidden"><span>Previous updates</span><x-parish.icon name="chevron-down" class="size-5 shrink-0 transition-transform group-open:rotate-180" /></summary>
        <div class="mt-4 grid gap-3 border-t border-navy/10 pt-4">
            @if($run && ! $hasOutlook)<p class="text-sm text-muted">{{ $run->message }}</p>@endif
            @forelse($runs as $previous)
                <div class="grid gap-1 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"><p class="font-medium">{{ match($previous->status) { 'ready' => 'Estimates prepared', 'unreliable' => 'Prepared with extra caution', 'insufficient_data' => 'More records were needed', default => 'Update was unavailable' } }}</p><time class="text-xs text-muted" datetime="{{ $previous->created_at->toIso8601String() }}">{{ $previous->created_at->format('M j, Y · g:i A') }}</time><p class="text-xs text-muted sm:col-span-2">Records through {{ ($previousMonth = collect($previous->history)->last()['month'] ?? null) ? \Carbon\CarbonImmutable::parse($previousMonth.'-01')->format('F Y') : 'not available' }}</p></div>
            @empty
                <p class="text-sm text-muted">No previous updates.</p>
            @endforelse
        </div>
    </details>
@endsection
