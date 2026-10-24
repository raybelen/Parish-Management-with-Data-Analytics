@php
    $maximum = max([1, ...array_map(fn ($point) => $point['value'] ?? 0, $points)]);
    $hasValues = count(array_filter($points, fn ($point) => $point['value'] !== null)) > 0;
@endphp
@if($hasValues)
    <figure class="mt-6">
        <div class="overflow-x-auto pb-2" tabindex="0" aria-label="{{ $chartLabel }}; scroll horizontally on small screens">
            <div class="grid min-w-[32rem] items-end gap-2" style="grid-template-columns: repeat({{ count($points) }}, minmax(0, 1fr))" role="list" aria-label="{{ $chartLabel }}">
                @foreach($points as $point)
                    <div class="grid gap-2 text-center" role="listitem" aria-label="{{ $point['label'] }}: {{ $point['value'] === null ? 'Not available' : number_format($point['value']).($point['partial'] ? ', partial total' : '') }}">
                        <span class="text-xs font-semibold tabular-nums">{{ $point['value'] === null ? '—' : number_format($point['value']).($point['partial'] ? '*' : '') }}</span>
                        <div class="flex h-36 items-end justify-center border-b border-navy/10" aria-hidden="true">
                            @if($point['value'] === null)
                                <div class="h-full w-full max-w-12 rounded-t-md border border-dashed border-navy/15 bg-slate-50"></div>
                            @else
                                <div @class(['w-full max-w-12 rounded-t-md', 'bg-navy/85' => !$point['partial'], 'bg-gold/70' => $point['partial']]) style="height: {{ $point['value'] === 0 ? '2px' : round($point['value'] / $maximum * 100, 2).'%' }}"></div>
                            @endif
                        </div>
                        <span class="text-xs text-muted">{{ $point['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <figcaption class="mt-3 text-xs text-muted">Verified totals. A dash means the total is unavailable.@if(collect($points)->contains('partial', true)) * Partial year.@endif</figcaption>
    </figure>
@else
    <div class="mt-5 rounded-xl bg-slate-50 px-5 py-8 text-center">
        <p class="font-semibold">No verified totals to display</p>
        <p class="mt-2 text-sm text-muted">Choose another year or review the monthly records below.</p>
    </div>
@endif
