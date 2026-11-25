@extends('admin.layout')
@section('title', 'Reports & exports')
@section('content')
    <div class="flex flex-wrap gap-3 text-sm">
        @can('donations.view')<a class="admin-secondary" href="{{ route('admin.donations.index') }}">Donation reports</a>@endcan
        @can('finance.view')<a class="admin-secondary" href="{{ route('admin.revenue.index') }}">Parish Revenue</a>@endcan
    </div>
    @if($totals)
        <section class="admin-card grid gap-5" aria-labelledby="summary-heading">
            <div><h2 id="summary-heading" class="text-2xl">Service payment summary</h2><p class="mt-2 text-sm text-muted">Appointment payments only. Donations and Mass collections are included in Parish Revenue. These totals include test transactions.</p></div>
            <form class="grid items-end gap-4 sm:grid-cols-3" method="GET" action="{{ route('admin.reports.index') }}">
                <x-admin.field label="From" name="from" type="date" :value="$from" />
                <x-admin.field label="Through" name="to" type="date" :value="$to" />
                <button class="admin-secondary">Refresh summary</button>
            </form>
            <p class="text-sm text-muted">Summary period: {{ $from }} through {{ $to }}. Service and status filters apply only to the preview and download.</p>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach(['cash_collected' => 'Service payment collections', 'refunds' => 'Service payment refunds', 'completed_revenue' => 'Net collections for completed services', 'outstanding' => 'Outstanding balances · all dates'] as $key => $label)
                    <div class="rounded-xl bg-slate-50 p-4"><p class="text-sm text-muted">{{ $label }}</p><p class="mt-3 text-2xl font-semibold tabular-nums">₱{{ number_format($totals[$key], 2) }}</p></div>
                @endforeach
            </div>
            <p class="text-sm leading-6 text-muted">Collections use payment dates; refunds use refund dates. {{ $totals['completed'] }} completed services in this period. Completed-service collections use the completion date and net payments recorded to date, including later payments and refunds.</p>
        </section>
    @endif
    @php
        $reportOptions = [];
        if (auth()->user()->can('appointments.view')) {
            $reportOptions['appointments'] = 'Appointments';
        }
        if (auth()->user()->can('records.view')) {
            $reportOptions['records'] = 'Service / historical records';
        }
        if (auth()->user()->can('finance.view')) {
            $reportOptions['payments'] = 'Payments and refunds';
            $reportOptions['outstanding'] = 'Unpaid balances (all dates)';
            if (config('office.analytics_enabled')) {
                $reportOptions['monthly'] = 'Completed services by month';
                if (auth()->user()->can('forecasts.manage')) {
                    $reportOptions['forecast'] = 'Forecast report';
                }
            }
        }
        $selectedType = array_key_exists($type, $reportOptions) ? $type : array_key_first($reportOptions);
        $reportDescriptions = [
            'appointments' => 'Uses request creation dates. Includes saved appointment references, contacts, service types and appointment dates.',
            'records' => 'Uses service dates. Includes current and historical register entries, including archived entries marked with an archive date. This is a directory export, not a complete backup of every service-specific field.',
            'payments' => 'Uses payment and refund dates. Amounts are in PHP; kind identifies payments and refunds. Receipt numbers link entries to system receipts. Test transactions, including their refunds, are labeled Yes.',
            'outstanding' => 'Current unpaid balances across all dates for pending, confirmed and completed appointments. Includes test payments in the balance calculation.',
            'monthly' => 'Uses completion dates and saved appointments, not historical register totals. Net collections include later payments and refunds, including test transactions. Blank values mean a month is marked as missing.',
            'forecast' => 'Uses the date each forecast was generated. The preview and CSV show saved monthly estimates with whole-number counts and planning ranges rounded outward. Ranges are not guaranteed. Full precision and model settings remain saved in the system.',
        ];
    @endphp
    <section class="admin-card grid gap-5" aria-labelledby="download-heading">
        <div><h2 id="download-heading" class="text-2xl">Prepare a report</h2><p class="mt-2 text-sm text-muted">Preview up to 10 rows, then download the full CSV for Excel.</p></div>
        @if($reportOptions)
            <form class="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-3" method="GET" action="{{ route('admin.reports.index') }}" data-report-form>
                <x-admin.field label="Report" name="type"><select class="admin-input" name="type">
                    @foreach($reportOptions as $value => $label)<option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>@endforeach
                </select></x-admin.field>
                <x-admin.field label="Service" name="service"><select class="admin-input" name="service"><option value="">All services</option>@foreach($services as $service)<option value="{{ $service->slug }}" @selected(($filters['service'] ?? '') === $service->slug)>{{ $service->name }}</option>@endforeach</select></x-admin.field>
                <fieldset data-report-status>
                    <x-admin.field label="Appointment status" name="status"><select class="admin-input" name="status"><option value="">All applicable statuses</option>
                        @foreach(\App\Models\Appointment::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status) data-outstanding-status="{{ in_array($status, ['pending', 'confirmed', 'completed']) ? '1' : '0' }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach
                    </select></x-admin.field>
                </fieldset>
                <fieldset class="grid gap-4 sm:col-span-2 sm:grid-cols-2" data-report-dates>
                    <x-admin.field label="From" name="from" type="date" :value="$from" />
                    <x-admin.field label="Through" name="to" type="date" :value="$to" />
                    <div class="flex flex-wrap gap-2 sm:col-span-2">
                        <button type="button" class="admin-secondary" data-report-from="{{ now()->startOfMonth()->toDateString() }}" data-report-to="{{ now()->toDateString() }}">This month</button>
                        <button type="button" class="admin-secondary" data-report-from="{{ now()->startOfYear()->toDateString() }}" data-report-to="{{ now()->toDateString() }}">This year</button>
                    </div>
                </fieldset>
                <div class="flex flex-wrap gap-2"><button class="admin-secondary" name="preview" value="1">Preview report</button><button class="admin-button" name="export" value="1">Download CSV</button></div>
                <div class="sm:col-span-2 xl:col-span-3" aria-live="polite">
                    @foreach($reportDescriptions as $value => $description)<p class="text-sm leading-6 text-muted" data-report-description="{{ $value }}" @if($selectedType !== $value) hidden @endif>{{ $description }}</p>@endforeach
                </div>
            </form>
        @else
            <p class="text-sm text-muted">Your account does not have permission to download these reports.</p>
        @endif
    </section>
    @if($preview !== null)
        <section class="admin-card min-w-0" data-report-preview aria-labelledby="preview-heading">
            <h2 id="preview-heading" class="text-2xl">Report preview</h2>
            <p class="mt-2 text-sm text-muted">{{ number_format($preview['count']) }} matching rows. Showing the first {{ count($preview['rows']) }}. The download uses the same filters and includes all matching rows. Saved records may change after this preview.</p>
            <p class="mt-2 text-sm font-medium text-amber-800" data-report-preview-stale hidden>Filters changed. Preview again to see the updated results.</p>
            @if($preview['count'])
                @if($type === 'forecast')
                    @include('admin.partials.forecast-report-preview')
                @else
                <div class="mt-4 overflow-x-auto"><table class="admin-table"><caption class="sr-only">First 10 rows of the selected report</caption><thead><tr>@foreach($preview['columns'] as $column)<th scope="col">{{ \Illuminate\Support\Str::headline($column) }}</th>@endforeach</tr></thead><tbody>
                    @foreach($preview['rows'] as $row)<tr>@foreach($row as $cell)<td class="max-w-xs break-words">{{ $cell ?? '—' }}</td>@endforeach</tr>@endforeach
                </tbody></table></div>
                @endif
            @else
                <p class="mt-4 text-sm">No records match these filters. Adjust the service or date range and try again.</p>
            @endif
        </section>
    @endif
@endsection
