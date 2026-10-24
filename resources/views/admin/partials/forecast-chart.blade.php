@php
    $observations = array_slice($run->history, -24);
    $predictions = $run->results[$metric]['forecast'];
    $values = array_merge(array_column($observations, 'count'), array_column($predictions, 'estimate'));
    $maximum = ceil(max(1, max($values), max(array_column($predictions, 'upper'))));
    $total = max(1, count($values) - 1);
    $coordinates = [];
    foreach ($values as $index => $value) {
        $coordinates[] = round(48 + $index * 620 / $total, 2).','.round(180 - $value * 145 / $maximum, 2);
    }
    $upper = $lower = [];
    foreach ($predictions as $index => $point) {
        $x = round(48 + (count($observations) + $index) * 620 / $total, 2);
        $upper[] = $x.','.round(180 - $point['upper'] * 145 / $maximum, 2);
        $lower[] = $x.','.round(180 - $point['lower'] * 145 / $maximum, 2);
    }
    $boundary = 48 + (count($observations) - 1) * 620 / $total;
    $lastActual = \Carbon\CarbonImmutable::parse(end($observations)['month'].'-01');
@endphp
<figure class="my-5">
    <div class="overflow-x-auto rounded-xl bg-slate-50 p-3" tabindex="0" aria-label="Activity chart; scroll horizontally on small screens">
        <svg viewBox="0 0 710 225" role="img" aria-label="{{ $label }}: recorded totals, estimated counts and planning range" class="min-w-[26rem] w-full">
            <line x1="48" x2="668" y1="180" y2="180" stroke="#d7dbe1" />
            <line x1="48" x2="668" y1="35" y2="35" stroke="#e5e7eb" stroke-dasharray="3 5" />
            <line x1="{{ $boundary }}" x2="{{ $boundary }}" y1="25" y2="180" stroke="#c7cbd2" stroke-dasharray="3 5" />
            <text x="5" y="40" fill="#626974" font-size="12">{{ number_format($maximum) }}</text>
            <text x="25" y="184" fill="#626974" font-size="12">0</text>
            <polygon points="{{ implode(' ', array_merge($upper, array_reverse($lower))) }}" fill="#a17c29" fill-opacity="0.16" />
            <polyline points="{{ implode(' ', array_slice($coordinates, 0, count($observations))) }}" fill="none" stroke="#13233f" stroke-width="2.5" />
            <polyline points="{{ implode(' ', array_slice($coordinates, count($observations) - 1)) }}" fill="none" stroke="#967020" stroke-width="2.5" stroke-dasharray="6 4" />
            <text x="48" y="207" fill="#626974" font-size="12">{{ \Carbon\CarbonImmutable::parse($observations[0]['month'].'-01')->format('M Y') }}</text>
            <text x="{{ $boundary - 8 }}" y="207" text-anchor="end" fill="#626974" font-size="12">{{ $lastActual->format('M Y') }}</text>
            <text x="668" y="207" text-anchor="end" fill="#626974" font-size="12">{{ $lastActual->addMonths(count($predictions))->format('M Y') }}</text>
        </svg>
    </div>
    <figcaption class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-muted">
        <span class="inline-flex items-center gap-2"><span class="w-5 border-t-2 border-navy" aria-hidden="true"></span>Recorded</span>
        <span class="inline-flex items-center gap-2"><span class="w-5 border-t-2 border-dashed border-gold" aria-hidden="true"></span>Estimated</span>
        <span class="inline-flex items-center gap-2"><span class="h-3 w-5 rounded-sm bg-gold/20" aria-hidden="true"></span>Planning range</span>
    </figcaption>
</figure>
