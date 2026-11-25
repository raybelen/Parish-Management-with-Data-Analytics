@php
    $forecastRows = collect($preview['rows'])->map(fn ($row) => array_combine($preview['columns'], $row));
    $serviceNames = $services->pluck('name', 'slug');
    $statusLabels = [
        'ready' => 'Estimates prepared',
        'unreliable' => 'Use with caution',
        'insufficient_data' => 'More records needed',
        'unavailable' => 'Estimates unavailable',
    ];
@endphp
<p class="mt-3 text-sm text-muted">Expected counts are rounded to whole numbers. Planning ranges are rounded outward and are not guaranteed. Each saved run may include several months.</p>
<div class="mt-4 overflow-x-auto">
    <table class="admin-table">
        <caption class="sr-only">Saved monthly forecast estimates</caption>
        <thead><tr><th scope="col">Service</th><th scope="col">Forecast month</th><th scope="col">Expected count</th><th scope="col">Planning range</th><th scope="col">Generated</th><th scope="col">Status</th></tr></thead>
        <tbody>
            @foreach($forecastRows as $forecast)
                <tr>
                    <td>{{ $serviceNames[$forecast['service']] ?? \Illuminate\Support\Str::headline($forecast['service']) }}</td>
                    <td class="whitespace-nowrap">{{ $forecast['forecast_month'] ? \Carbon\CarbonImmutable::parse($forecast['forecast_month'].'-01')->format('M Y') : 'Not available' }}</td>
                    <td class="tabular-nums">{{ $forecast['predicted_count'] !== null ? number_format(round($forecast['predicted_count'])) : 'Not available' }}</td>
                    <td class="whitespace-nowrap tabular-nums">@if($forecast['planning_lower'] !== null && $forecast['planning_upper'] !== null){{ number_format(floor($forecast['planning_lower'])) }}–{{ number_format(ceil($forecast['planning_upper'])) }}@else Not available @endif</td>
                    <td class="whitespace-nowrap">{{ \Carbon\CarbonImmutable::parse($forecast['generated_at'])->format('M j, Y · g:i A') }}</td>
                    <td>{{ $statusLabels[$forecast['status']] ?? \Illuminate\Support\Str::headline($forecast['status']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<details class="mt-5 rounded-xl border border-navy/10 p-4">
    <summary class="cursor-pointer text-sm font-semibold">View saved forecast details</summary>
    <div class="mt-4 grid gap-4">
        @foreach($forecastRows->unique('run_id') as $forecast)
            @php
                $settings = json_decode($forecast['configuration'], true) ?? [];
                $evaluation = json_decode($forecast['evaluation'], true) ?? [];
            @endphp
            <div class="rounded-xl bg-slate-50 p-4 text-sm">
                <p class="font-semibold">{{ $serviceNames[$forecast['service']] ?? \Illuminate\Support\Str::headline($forecast['service']) }} · {{ \Carbon\CarbonImmutable::parse($forecast['generated_at'])->format('M j, Y · g:i A') }}</p>
                <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div><dt class="text-muted">Records through</dt><dd>{{ $forecast['data_through'] ? \Carbon\CarbonImmutable::parse($forecast['data_through'].'-01')->format('F Y') : 'Not available' }}</dd></div>
                    <div><dt class="text-muted">Forecast period</dt><dd>{{ isset($settings['forecast_horizon']) ? $settings['forecast_horizon'].' months' : 'Not available' }}</dd></div>
                    <div><dt class="text-muted">Typical past prediction error</dt><dd>{{ isset($evaluation['mae']) ? number_format($evaluation['mae'], 1).' services per month' : 'Not evaluated' }}</dd></div>
                    <div><dt class="text-muted">Comparison with simpler estimates</dt><dd>{{ ! array_key_exists('outperformed_baselines', $evaluation) ? 'Not evaluated' : ($evaluation['outperformed_baselines'] ? 'More accurate in the saved test' : 'Did not outperform both comparisons') }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-muted">Saved run #{{ $forecast['run_id'] }}. Full precision, technical settings and evaluation remain saved in the system.</p>
            </div>
        @endforeach
    </div>
</details>
