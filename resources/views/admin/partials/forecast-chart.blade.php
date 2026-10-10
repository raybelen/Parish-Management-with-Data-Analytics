@php
    $observations = array_slice($run->history, -24);
    $predictions = $run->results[$metric]['forecast'];
    $values = array_merge(array_column($observations, $metric), array_column($predictions, 'estimate'));
    $maximum = max(1, max($values));
    $total = max(1, count($values) - 1);
    $coordinates = [];
    foreach ($values as $index => $value) {
        $coordinates[] = round(55 + $index * 620 / $total, 2).','.round(150 - $value * 125 / $maximum, 2);
    }
@endphp
<figure class="mb-5 rounded-lg bg-slate-50 p-4">
    <svg viewBox="0 0 710 180" role="img" aria-label="Historical {{ $label }} followed by SARIMA estimates" class="w-full">
        <line x1="55" x2="675" y1="150" y2="150" stroke="#d7dbe1" />
        <line x1="55" x2="675" y1="25" y2="25" stroke="#e5e7eb" />
        <text x="3" y="30" fill="#626974" font-size="10">{{ number_format($maximum, 0) }}</text><text x="35" y="152" fill="#626974" font-size="10">0</text>
        <polyline points="{{ implode(' ', array_slice($coordinates, 0, count($observations))) }}" fill="none" stroke="#13233f" stroke-width="2.5" />
        <polyline points="{{ implode(' ', array_slice($coordinates, count($observations) - 1)) }}" fill="none" stroke="#a17c29" stroke-width="2.5" stroke-dasharray="6 4" />
        <text x="55" y="172" fill="#626974" font-size="10">{{ $observations[0]['month'] }}</text><text x="570" y="172" fill="#626974" font-size="10">Forecast →</text>
    </svg><figcaption class="text-xs text-muted">Solid navy: historical values. Dashed gold: estimated values. Prediction intervals are listed below.</figcaption>
</figure>
