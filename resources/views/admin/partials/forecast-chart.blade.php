@php
    $observations = array_slice($run->history, -24);
    $predictions = $run->results[$metric]['forecast'];
    $values = array_merge(array_column($observations, 'count'), array_column($predictions, 'estimate'));
    $maximum = ceil(max(1, max($values)));
    $total = max(1, count($values) - 1);
    $coordinates = [];
    foreach ($values as $index => $value) {
        $coordinates[] = round(48 + $index * 620 / $total, 2).','.round(180 - $value * 145 / $maximum, 2);
    }
    $recordedCoordinates = array_slice($coordinates, 0, count($observations));
    $expectedCoordinates = array_slice($coordinates, count($observations) - 1);
    $boundary = 48 + (count($observations) - 1) * 620 / $total;
    $lastActual = \Carbon\CarbonImmutable::parse(end($observations)['month'].'-01');
    $chartPoints = collect($coordinates)->map(function (string $coordinate, int $index) use ($observations, $predictions, $lastActual): array {
        [$x, $y] = explode(',', $coordinate);
        $isRecorded = $index < count($observations);
        $predictionIndex = $index - count($observations);
        $month = $isRecorded
            ? \Carbon\CarbonImmutable::parse($observations[$index]['month'].'-01')
            : $lastActual->addMonths($predictionIndex + 1);

        return [
            'x' => $x,
            'y' => $y,
            'label' => $month->format('F Y'),
            'short_label' => $month->format("M 'y"),
            'value' => $isRecorded ? $observations[$index]['count'] : $predictions[$predictionIndex]['estimate'],
            'kind' => $isRecorded ? 'Recorded total' : 'Expected total',
            'range' => $isRecorded ? null : floor($predictions[$predictionIndex]['lower']).'–'.ceil($predictions[$predictionIndex]['upper']),
        ];
    })->all();
    $monthLabelIndexes = collect(range(0, count($chartPoints) - 1))
        ->filter(fn (int $index): bool => ($index % 4 === 0 && $index < count($chartPoints) - 2) || $index === count($chartPoints) - 1)
        ->all();
@endphp
<figure class="my-6">
    <div class="relative overflow-x-auto rounded-2xl border border-navy/8 bg-slate-50 p-3 sm:p-5" tabindex="0" aria-label="Interactive activity chart; scroll horizontally on small screens" data-forecast-chart>
        <svg viewBox="0 0 710 255" role="img" aria-label="{{ $label }}: recorded and estimated totals by month" class="min-w-[48rem] w-full" data-forecast-chart-svg>
            @foreach([35, 83, 132, 180] as $gridY)
                <line x1="48" x2="668" y1="{{ $gridY }}" y2="{{ $gridY }}" stroke="#dfe3e8" stroke-dasharray="3 6" />
            @endforeach
            <line x1="{{ $boundary }}" x2="{{ $boundary }}" y1="25" y2="180" stroke="#B8964A" stroke-dasharray="4 5" />
            <text x="5" y="40" fill="#5c6573" font-size="12">{{ number_format($maximum) }}</text>
            <text x="25" y="184" fill="#5c6573" font-size="12">0</text>
            <text x="{{ $boundary + 8 }}" y="24" fill="#80662e" font-size="11" font-weight="600">ESTIMATED</text>
            <polyline points="{{ implode(' ', $recordedCoordinates) }}" fill="none" stroke="#13233f" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            <polyline points="{{ implode(' ', $expectedCoordinates) }}" fill="none" stroke="#967020" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            <line x1="0" x2="0" y1="25" y2="180" stroke="#13233f" stroke-opacity="0.25" visibility="hidden" data-chart-guide />
            <circle cx="0" cy="0" r="5" fill="#fff" stroke="#13233f" stroke-width="2.5" visibility="hidden" data-chart-marker />
            @foreach($chartPoints as $point)
                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="{{ $point['kind'] === 'Recorded total' ? '#13233f' : '#B8964A' }}" stroke="#fff" stroke-width="1.5" aria-hidden="true" />
                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="11" fill="transparent" tabindex="0" role="button"
                    aria-label="{{ $point['label'] }}: {{ $point['kind'] }}, {{ number_format(round($point['value'])) }}{{ $point['range'] ? ', possible range '.$point['range'] : '' }}"
                    data-chart-point data-x="{{ $point['x'] }}" data-y="{{ $point['y'] }}" data-label="{{ $point['label'] }}"
                    data-kind="{{ $point['kind'] }}" data-value="{{ number_format(round($point['value'])) }}" data-range="{{ $point['range'] }}" />
            @endforeach
            @foreach($monthLabelIndexes as $index)
                <line x1="{{ $chartPoints[$index]['x'] }}" x2="{{ $chartPoints[$index]['x'] }}" y1="180" y2="185" stroke="#9aa1aa" />
                <text x="{{ $chartPoints[$index]['x'] }}" y="207" text-anchor="middle" fill="#5c6573" font-size="11">{{ $chartPoints[$index]['short_label'] }}</text>
            @endforeach
            <text x="358" y="239" text-anchor="middle" fill="#5c6573" font-size="12" font-weight="600">Month</text>
        </svg>
        <div class="pointer-events-none absolute z-10 hidden min-w-36 rounded-xl bg-navy px-3 py-2.5 text-xs text-white shadow-xl" role="status" aria-live="polite" data-chart-tooltip>
            <p class="font-semibold" data-tooltip-label></p>
            <p class="mt-1.5 flex items-center justify-between gap-5"><span class="text-white/70" data-tooltip-kind></span><strong class="tabular-nums" data-tooltip-value></strong></p>
            <p class="mt-1 hidden border-t border-white/15 pt-1 text-white/70" data-tooltip-range></p>
        </div>
    </div>
    <figcaption class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs font-medium text-muted">
        <span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full bg-navy ring-2 ring-white" aria-hidden="true"></span>Recorded totals</span>
        <span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full bg-gold ring-2 ring-white" aria-hidden="true"></span>Estimated totals</span>
    </figcaption>
</figure>
