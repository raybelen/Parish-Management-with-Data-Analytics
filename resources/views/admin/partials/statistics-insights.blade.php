<section class="admin-card" aria-label="Planning recommendations">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-2xl">What this means for planning</h2>
            <p class="mt-2 text-sm text-muted">{{ $serviceName }} · {{ $year }}. Activity measures service volume, not service quality or a growth target.</p>
        </div>
        @if($insights['through'])<span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-muted">Latest verified month: {{ $insights['through'] }}</span>@endif
    </div>
    @if($monthlyService && $insights['peak'])
        <p class="mt-5 rounded-xl bg-slate-50 p-4 text-sm leading-6"><strong>{{ implode(', ', $insights['peak']['labels']) }}</strong> {{ count($insights['peak']['labels']) === 1 ? 'had' : 'each had' }} the highest recorded monthly count: <strong>{{ number_format($insights['peak']['count']) }}</strong>. {{ count($insights['peak']['labels']) === 1 ? 'That month accounts' : 'Each accounts' }} for {{ number_format($insights['peak']['share'], 1) }}% of this year's verified total{{ $summary['months'] < 12 ? ' so far' : '' }}.</p>
    @endif
    <ul class="mt-5 grid list-none gap-4 lg:grid-cols-2">
        @foreach($insights['recommendations'] as $recommendation)
            <li class="rounded-xl border border-navy/10 p-4">
                <h3 class="font-sans text-sm font-semibold">{{ $recommendation['title'] }}</h3>
                <p class="mt-2 text-sm leading-6 text-muted">{{ $recommendation['text'] }}</p>
                @if($recommendation['review'])<a href="#monthly-records" class="mt-3 inline-flex text-sm font-semibold underline underline-offset-4">Review monthly records</a>@endif
            </li>
        @endforeach
    </ul>
</section>

@if($monthlyService)
    <section class="admin-card">
        <h2 class="text-2xl">Typical seasonal pattern</h2>
        <p class="mt-2 text-sm leading-6 text-muted">Typical counts for each calendar month in {{ $year - 5 }}–{{ $year - 1 }}. Use this to understand recurring busy periods alongside upcoming bookings.</p>
        @if(collect($insights['seasonal'])->whereNotNull('value')->isNotEmpty())
            @include('admin.partials.statistics-chart', ['points' => $insights['seasonal'], 'comparisonYear' => null, 'chartLabel' => $serviceName.' typical monthly counts', 'chartNote' => 'Typical means the middle count (median) from five verified years. A dash means fewer than five years are available. This is historical context, not a forecast.'])
        @else
            <p class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-muted">Five verified prior years for the same calendar month are needed to describe a typical pattern.</p>
        @endif
    </section>

    <section class="admin-card">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-2xl">Months to review</h2>
                <p class="mt-2 text-sm leading-6 text-muted">Unusual counts compared with the same month in the five preceding years. A flag calls for review; it does not prove a recording error.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs">{{ count($insights['anomalies']) }} flagged · {{ $insights['screened'] }}/{{ $insights['verified'] }} verified months checked</span>
        </div>
        @if($insights['anomalies'] !== [])
            <div class="mt-4 overflow-x-auto"><table class="admin-table"><caption class="sr-only">Unusual {{ $serviceName }} counts in {{ $year }}</caption><thead><tr><th>Month</th><th>Recorded</th><th>Typical count</th><th>Change</th></tr></thead><tbody>
                @foreach($insights['anomalies'] as $anomaly)
                    <tr><td>{{ $anomaly['month'] }} {{ $year }}</td><td class="font-semibold">{{ number_format($anomaly['actual']) }}</td><td>{{ number_format($anomaly['typical']) }}</td><td>{{ $anomaly['direction'] }} than usual</td></tr>
                @endforeach
            </tbody></table></div>
            <a href="#monthly-records" class="mt-4 inline-flex text-sm font-semibold underline underline-offset-4">Check the source records</a>
        @else
            <p class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-muted">{{ $insights['screened'] === 0 ? 'Not enough verified history to check for unusual months.' : 'No unusual counts were flagged among the checked months. Continue verifying the source records.' }}</p>
        @endif
    </section>
@endif

<details class="admin-card">
    <summary class="cursor-pointer text-sm font-semibold">Data coverage and calculation details</summary>
    <div class="mt-4 grid gap-3 text-sm leading-6 text-muted">
        <p>{{ $insights['period'] }} {{ $year }}: {{ $insights['verified'] }} verified months out of {{ $insights['expected'] }} completed months; {{ $insights['drafts'] }} draft months. Zero means no services occurred. Unverified, missing, current, and future months are excluded.</p>
        @if($insights['missing'] !== [])<p>Months awaiting verification: {{ implode(', ', $insights['missing']) }}.</p>@endif
        <p>Completeness measures record coverage, not a guarantee that every source entry is correct. Check the monthly register references and correction history below.</p>
        @if($monthlyService)
            <p>Year comparisons use January through the last completed month of the selected year, with every month verified in both years. Percentages are unavailable when the earlier total is zero. Averages and peak shares use only verified months.</p>
            <p>Unusual-count screening uses the median and median absolute deviation (MAD) of the same calendar month in the previous five years. A count is flagged when its distance from the median exceeds the larger of 3 counts or 3.5 × MAD ÷ 0.6745. The 3-count minimum avoids flagging tiny changes in sparse records and handles zero MAD. This is a descriptive review rule with no guaranteed detection rate; valid changes in schedules can also trigger it.</p>
            <p>Method reference: <a href="https://itl.nist.gov/div898/handbook/eda/section3/eda35h.htm" class="underline underline-offset-4" target="_blank" rel="noopener noreferrer">NIST guidance on potential outliers</a>. SARIMA remains the forecasting method; these summaries do not change its inputs.</p>
        @else
            <p>Group ceremonies use annual totals and complete-year comparisons. Partial annual totals can reflect ceremony scheduling, so they are not used to infer monthly seasonality or unusual monthly activity.</p>
        @endif
    </div>
</details>
