@extends('admin.layout')
@section('title', 'Service forecasts')
@section('content')
    @php
        $serviceName = \Illuminate\Support\Str::before($services[$service], ' (');
        $unit = ['baptism' => 'people', 'wedding' => 'couples', 'funeral' => 'Funeral Masses'][$service];
        $cutoffDate = $cutoff ? \Carbon\CarbonImmutable::parse($cutoff.'-01') : null;
        $hasOutlook = !$stale && !empty($run?->results['count']['forecast']);
        $historical = $cutoff && $cutoff < $latestCompleteMonth;
        $reviewUrl = route('admin.statistics.index', ['service' => $service, 'year' => $cutoffDate?->addMonth()->year ?? now()->year]).'#monthly-records';
    @endphp
    <p class="text-sm text-muted">Plan parish services with a six-month outlook and a range of possible counts.</p>
    <form method="GET" class="admin-card grid items-end gap-4 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
        <x-admin.field label="Service" name="service"><select name="service" class="admin-input">@foreach($services as $code => $label)<option value="{{ $code }}" @selected($service === $code)>{{ $label }}</option>@endforeach</select></x-admin.field>
        <button class="admin-button">View forecast</button>
        @can('reports.view')<a class="admin-secondary" href="{{ route('admin.statistics.index', ['service' => $service]) }}">View actual totals</a>@endcan
    </form>

    <section class="admin-card">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl">{{ $serviceName }} outlook</h2>
                <p class="mt-2 text-sm text-muted">Based on records through {{ $cutoffDate?->format('F Y') ?? '—' }}.</p>
            </div>
            <form method="POST" action="{{ route('admin.forecasts.store') }}" data-forecast-form>
                @csrf<input type="hidden" name="service" value="{{ $service }}">
                <button class="admin-secondary">{{ $run ? 'Update estimates' : 'Prepare estimates' }}</button>
                <p class="mt-2 hidden text-xs text-muted" role="status" data-forecast-progress>Preparing your outlook. This may take a moment.</p>
            </form>
        </div>
        @if($historical)
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                <p class="font-semibold">Historical outlook</p>
                <p class="mt-1">These estimates begin after {{ $cutoffDate->format('F Y') }}. Add recent monthly totals before using them for current planning.</p>
                @can('reports.view')<a class="mt-2 inline-flex font-semibold underline underline-offset-4" href="{{ $reviewUrl }}">Review recent records</a>@endcan
            </div>
        @endif
        @if($stale)
            <p class="mt-4 text-sm font-medium text-amber-900">The saved forecast is outdated. Update estimates to include the latest record changes.</p>
        @elseif($hasOutlook && $run->status === 'unreliable')
            <p class="mt-4 text-sm text-amber-900"><strong>Use with caution.</strong> Past predictions were less dependable than a simple comparison. Allow extra flexibility when planning this service.</p>
        @elseif(!$hasOutlook)
            <div class="mt-5 rounded-xl bg-slate-50 p-5">
                <p class="font-semibold">{{ $run?->status === 'unavailable' ? 'Estimates are temporarily unavailable' : 'No outlook available yet' }}</p>
                <p class="mt-2 text-sm text-muted">{{ $run?->status === 'unavailable' ? 'Please try updating the estimates again. Contact your system administrator if the issue continues.' : ($run ? 'More complete monthly records are needed to prepare an outlook.' : 'Prepare estimates once your monthly records are complete.') }}</p>
                @can('reports.view')<a class="mt-3 inline-flex text-sm font-semibold underline underline-offset-4" href="{{ $reviewUrl }}">Review monthly records</a>@endcan
            </div>
        @endif
    </section>

    @if($hasOutlook)
        @php
            $points = $run->results['count']['forecast'];
            $evaluation = $run->results['count']['evaluation'];
            $firstMonth = \Carbon\CarbonImmutable::parse(collect($run->history)->last()['month'].'-01')->addMonth();
            $peakIndex = array_search(max(array_column($points, 'estimate')), array_column($points, 'estimate'));
        @endphp
        <section class="grid gap-4 sm:grid-cols-3" aria-label="Forecast at a glance">
            <div class="admin-card">
                <p class="text-sm text-muted">{{ $firstMonth->format('F Y') }} estimate</p>
                <p class="mt-3 text-3xl font-semibold tabular-nums">{{ number_format(round($points[0]['estimate'])) }}</p>
                <p class="mt-2 text-xs text-muted">{{ $unit }} · estimated monthly count</p>
            </div>
            <div class="admin-card">
                <p class="text-sm text-muted">Highest estimated month</p>
                <p class="mt-3 text-3xl font-semibold">{{ $firstMonth->addMonths($peakIndex)->format('F') }}</p>
                <p class="mt-2 text-xs text-muted">About {{ number_format(round($points[$peakIndex]['estimate'])) }} {{ $unit }} in {{ $firstMonth->addMonths($peakIndex)->format('Y') }}</p>
            </div>
            <div class="admin-card">
                <p class="text-sm text-muted">Past prediction error</p>
                <p class="mt-3 text-3xl font-semibold tabular-nums">{{ number_format($evaluation['mae'], 1) }}</p>
                <p class="mt-2 text-xs text-muted">{{ $unit }} per month, on average, in past testing. Smaller is better.</p>
            </div>
        </section>

        <section class="admin-card">
            <h2 class="text-2xl">Activity and outlook</h2>
            <p class="mt-2 text-sm text-muted">Recent recorded totals alongside the next six estimated months.</p>
            @include('admin.partials.forecast-chart', ['run' => $run, 'metric' => 'count', 'label' => $services[$service]])
            <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Month</th><th>Estimated {{ $unit }}</th><th>Planning range</th></tr></thead><tbody>
                @foreach($points as $index => $point)
                    <tr><td>{{ $firstMonth->addMonths($index)->format('F Y') }}</td><td class="font-semibold tabular-nums">{{ number_format(round($point['estimate'])) }}</td><td class="tabular-nums">{{ number_format(floor($point['lower'])) }} – {{ number_format(ceil($point['upper'])) }}</td></tr>
                @endforeach
            </tbody></table></div>
            <p class="mt-4 text-xs leading-5 text-muted">Use the range to allow for changes in demand. Actual counts can fall outside it. Updated {{ $run->created_at->format('M j, Y') }}.</p>
        </section>
    @endif

    <details class="admin-card" id="forecast-details">
        <summary class="cursor-pointer text-sm font-semibold">Forecast accuracy and details</summary>
        <div class="mt-5 grid gap-5">
            @if($hasOutlook)
                <p class="text-sm text-muted">Tested against actual counts from {{ \Carbon\CarbonImmutable::parse($evaluation['test_start'].'-01')->format('F Y') }} to {{ \Carbon\CarbonImmutable::parse($evaluation['test_end'].'-01')->format('F Y') }}. Each prediction used only earlier records.</p>
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Comparison</th><th>Average error</th></tr></thead><tbody>
                    @foreach(['mae' => 'Our SARIMA forecast', 'seasonal_baseline_mae' => 'Same month in the previous year', 'recent_mean_mae' => 'Recent 12-month average'] as $key => $label)
                        <tr><td>{{ $label }}</td><td>{{ number_format($evaluation[$key], 2) }}</td></tr>
                    @endforeach
                </tbody></table></div>
                <p class="text-xs text-muted">Errors measure the difference from actual monthly counts, not an accuracy percentage. Smaller is better.</p>
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Months ahead</th><th>Average error</th><th>Within planning range</th></tr></thead><tbody>
                    @foreach($evaluation['by_horizon'] as $row)<tr><td>{{ $row['horizon'] }}</td><td>{{ number_format($row['mae'], 2) }}</td><td>{{ $row['covered'] }} of {{ $row['total'] }} tested months</td></tr>@endforeach
                </tbody></table></div>
                <p class="text-xs leading-5 text-muted">The same 12 months are tested at six lead times, not 72 independent months. Ranges use the largest error at each lead time during {{ $evaluation['calibration_start'] }}–{{ $evaluation['calibration_end'] }}. Calculations use unrounded estimates.</p>
            @endif
            <details class="rounded-xl border border-navy/10 p-4">
                <summary class="cursor-pointer text-sm font-semibold">Method and update history</summary>
                <p class="mt-4 text-xs leading-5 text-muted">SARIMA {{ json_encode(config('forecast.services.'.$service.'.order')) }} × {{ json_encode(config('forecast.services.'.$service.'.seasonal_order')) }}. {{ config('forecast.services.'.$service.'.log_transform') ? 'Log-transformed counts; back-transformed median estimates.' : 'Original count scale.' }} Model orders stay fixed; parameters are refitted using all consecutive confirmed history.</p>
                @if($run && !$hasOutlook)<p class="mt-3 text-sm">{{ $run->message }}</p>@endif
                @forelse($runs as $previous)
                    <p class="mt-3 border-t border-navy/10 pt-3 text-xs text-muted">{{ $previous->created_at->format('M j, Y H:i') }} · {{ match($previous->status) { 'ready' => 'Estimates prepared', 'unreliable' => 'Prepared with planning caution', 'insufficient_data' => 'More records needed', default => 'Update unavailable' } }} · Records through {{ collect($previous->history)->last()['month'] ?? 'not available' }}</p>
                @empty<p class="mt-3 text-sm text-muted">No previous updates.</p>@endforelse
            </details>
        </div>
    </details>
@endsection
