@extends('admin.layout')
@section('title', 'Service & revenue forecasts')
@section('content')
    <p class="text-sm text-muted">Monthly SARIMA estimates for completed services and their net collections. Historical imports are excluded. Forecasts are estimates, not guaranteed outcomes.</p>
    <form method="GET" class="admin-card flex flex-wrap items-end gap-4"><x-admin.field label="Forecast series" name="service"><select name="service" class="admin-input"><option value="">All services</option>@foreach($services as $service)<option value="{{ $service->slug }}" @selected(request('service') === $service->slug)>{{ $service->name }}</option>@endforeach</select></x-admin.field><button class="admin-secondary">View series</button></form>
    <section class="admin-card"><div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-2xl">{{ request('service') ? ucfirst(request('service')) : 'Overall' }} outlook</h2><p class="mt-2 text-sm text-muted">{{ count($history) }} completed monthly periods available · {{ $settings['forecast_horizon'] }}-month horizon</p></div><form method="POST" action="{{ route('admin.forecasts.store') }}">@csrf<input type="hidden" name="service" value="{{ request('service') }}"><button class="admin-button">Generate SARIMA forecast</button></form></div>
        @if($run)<div class="mt-5"><x-admin.status :value="$run->status" /><p class="mt-3 text-sm">{{ $run->message }}</p><p class="mt-2 text-xs text-muted">Generated {{ $run->created_at->format('M j, Y g:i A') }}. This saved result reflects the data and configuration at generation time.</p></div>@else<p class="mt-5 rounded-lg bg-slate-50 p-4 text-sm">No forecast generated. If eligible history is insufficient, the system will explain the limitation.</p>@endif
    </section>
    @if($run?->results)
        @foreach(['count' => 'Completed services', 'revenue' => 'Net completed-service collections (PHP)'] as $metric => $label)
            <section class="admin-card"><h2 class="mb-4 text-2xl">{{ $label }}</h2>
                @if(isset($run->results[$metric]['forecast']))
                    @include('admin.partials.forecast-chart', ['run' => $run, 'metric' => $metric, 'label' => $label])
                    <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Month</th><th>Estimate</th><th>95% prediction range</th></tr></thead><tbody>@foreach($run->results[$metric]['forecast'] as $index => $point)<tr><td>{{ \Carbon\CarbonImmutable::parse(collect($run->history)->last()['month'].'-01')->addMonths($index + 1)->format('Y-m') }}</td><td>{{ number_format($point['estimate'], $metric === 'count' ? 1 : 2) }}</td><td>{{ number_format($point['lower'], 2) }} – {{ number_format($point['upper'], 2) }}</td></tr>@endforeach</tbody></table></div>
                    <p class="mt-4 text-sm text-muted">Held-out MAE: {{ $run->results[$metric]['evaluation']['mae'] }} · Seasonal baseline MAE: {{ $run->results[$metric]['evaluation']['seasonal_baseline_mae'] }}. Lower error is better. {{ $run->results[$metric]['evaluation']['reliable'] ? 'Outperformed the baseline in this evaluation.' : 'Reliability warning: convergence or baseline comparison did not pass.' }}</p>
                @else<p class="text-sm text-muted">{{ $run->results[$metric]['error'] ?? 'Forecast unavailable for this series.' }}</p>@endif
            </section>
        @endforeach
    @endif
    <section class="admin-card"><h2 class="mb-4 text-2xl">Historical observations</h2>@if($history)@include('admin.partials.trend', ['history' => $history])@else<p class="text-sm text-muted">Set the first complete month of operational data in Parish settings. Do not mark months as complete unless their records are available.</p>@endif</section>
    <details class="admin-card"><summary class="cursor-pointer font-semibold">Model configuration & generation history</summary><p class="my-4 text-sm text-muted">SARIMA {{ json_encode($settings['order']) }} × {{ json_encode($settings['seasonal_order']) }}. Training window: {{ $settings['training_months'] }} months. Point estimates use a log transformation and are back-transformed medians.</p>@foreach($runs as $previous)<p class="border-b border-navy/10 py-3 text-sm">{{ $previous->created_at->format('M j, Y g:i A') }} · {{ $previous->service_type ?? 'Overall' }} · {{ str_replace('_', ' ', $previous->status) }}</p>@endforeach</details>
@endsection
