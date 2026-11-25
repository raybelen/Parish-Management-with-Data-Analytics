<?php

namespace App\Http\Controllers;

use App\Actions\ImportServiceRecords;
use App\Models\Appointment;
use App\Models\ForecastRun;
use App\Models\Payment;
use App\Models\ServiceRecord;
use App\Models\ServiceType;
use App\Models\User;
use App\Support\AnalyticsData;
use App\Support\CsvExport;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function index(Request $request, AnalyticsData $analytics): View|StreamedResponse
    {
        Gate::authorize('reports.view');
        $reportTypes = ['appointments', 'records', 'payments', 'outstanding'];
        if (config('office.analytics_enabled')) {
            array_push($reportTypes, 'monthly', 'forecast');
        }
        $data = $request->validate([
            'from' => ['exclude_if:type,outstanding', 'nullable', 'date_format:Y-m-d'], 'to' => ['exclude_if:type,outstanding', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['nullable', Rule::in($reportTypes)],
            'status' => [Rule::excludeIf(! in_array($request->input('type', 'appointments'), ['appointments', 'outstanding'], true)), 'nullable', Rule::in(Appointment::STATUSES)], 'service' => ['nullable', 'string', 'exists:service_types,slug'],
            'export' => ['nullable', 'boolean'],
            'preview' => ['nullable', 'boolean'],
        ]);
        $from = $data['from'] ?? now()->startOfYear()->toDateString();
        $to = $data['to'] ?? now()->toDateString();
        $type = $data['type'] ?? 'appointments';
        if ($from > $to && $type !== 'outstanding') {
            throw ValidationException::withMessages(['to' => 'The through date must be on or after the from date.']);
        }
        if ($type === 'appointments' && ($request->boolean('export') || $request->boolean('preview'))) {
            Gate::authorize('appointments.view');
        }
        if (in_array($type, ['payments', 'outstanding', 'monthly', 'forecast'], true)) {
            Gate::authorize('finance.view');
        }
        if ($type === 'records') {
            Gate::authorize('records.view');
        }
        if ($type === 'forecast') {
            Gate::authorize('forecasts.manage');
        }
        if ($request->boolean('export')) {
            [$columns, $rows] = $this->rows($type, $data, $from, $to, $analytics, $request->user());
            if ($type === 'forecast') {
                $serviceNames = ServiceType::query()->pluck('name', 'slug');
                $forecastColumns = $columns;
                $columns = ['Service', 'Forecast month', 'Expected count', 'Planning range', 'Generated', 'Status', 'Records through', 'Saved run', 'Data source'];
                $rows = $rows->map(function (array $values) use ($forecastColumns, $serviceNames): array {
                    $forecast = array_combine($forecastColumns, $values);

                    return [
                        $serviceNames[$forecast['service']] ?? ucfirst($forecast['service']),
                        $forecast['forecast_month'] ? CarbonImmutable::parse($forecast['forecast_month'].'-01')->format('M Y') : '',
                        $forecast['predicted_count'] !== null ? (int) round($forecast['predicted_count']) : '',
                        $forecast['planning_lower'] !== null && $forecast['planning_upper'] !== null
                            ? (int) floor($forecast['planning_lower']).' to '.(int) ceil($forecast['planning_upper']) : '',
                        CarbonImmutable::parse($forecast['generated_at'])->format('M j, Y g:i A'),
                        match ($forecast['status']) {
                            'ready' => 'Estimates prepared',
                            'unreliable' => 'Use with caution',
                            'insufficient_data' => 'More records needed',
                            default => 'Estimates unavailable',
                        },
                        $forecast['data_through'] ? CarbonImmutable::parse($forecast['data_through'].'-01')->format('M Y') : '',
                        $forecast['run_id'], $forecast['dataset'],
                    ];
                });
            }
            OfficeAudit::record('report.exported', null, [], ['type' => $type, 'from' => $type === 'outstanding' ? null : $from, 'to' => $type === 'outstanding' ? null : $to, 'service' => $data['service'] ?? null, 'status' => $data['status'] ?? null, 'source' => OfficeSettings::source()]);

            return CsvExport::download((OfficeSettings::demo() ? 'DEMO-' : '').$type.'-'.($type === 'outstanding' ? 'all-dates' : $from.'-'.$to).'.csv', $columns, $rows);
        }

        $preview = null;
        if ($request->boolean('preview')) {
            [$columns, $rows] = $this->rows($type, $data, $from, $to, $analytics, $request->user());
            $preview = ['columns' => $columns, 'rows' => [], 'count' => 0];
            foreach ($rows as $row) {
                if ($preview['count'] < 10) {
                    $preview['rows'][] = array_values($row);
                }
                $preview['count']++;
            }
        }

        return view('admin.reports', [
            'from' => $from, 'to' => $to,
            'type' => $type, 'filters' => $data, 'preview' => $preview,
            'totals' => $request->user()->can('finance.view') ? $analytics->totals($from, $to, $request->user()) : null,
            'services' => ServiceType::query()->orderBy('name')->get(),
        ]);
    }

    /** @param array<string, mixed> $filters
     * @return array{array<int, string>, iterable<array<array-key, mixed>>}
     */
    private function rows(string $type, array $filters, string $from, string $to, AnalyticsData $analytics, User $user): array
    {
        $label = OfficeSettings::demo() ? 'Test records' : 'Parish records - includes test payments';
        if ($type === 'records') {
            $records = ServiceRecord::query()->whereIn('source', ['archival', OfficeSettings::source()])->whereDate('service_date', '>=', $from)->whereDate('service_date', '<=', $to)
                ->when($filters['service'] ?? null, fn ($q, $service) => $q->where('service_type', $service))->orderBy('id')->lazy();

            return [[...ImportServiceRecords::COLUMNS, 'source', 'archived_at'], $records->map(fn ($record) => [...$record->only(ImportServiceRecords::COLUMNS), 'service_date' => $record->service_date->toDateString(), 'source' => $record->source, 'archived_at' => $record->archived_at?->toDateTimeString()])];
        }
        if ($type === 'monthly') {
            return [['dataset', 'month', 'completed_services', 'net_collections_for_completed_services'], collect($analytics->monthlyReport($from, $to, $filters['service'] ?? null, $user))->map(fn ($row) => [$label, ...array_values($row)])];
        }
        if ($type === 'forecast') {
            $runs = ForecastRun::query()->where('source', OfficeSettings::source())->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
                ->when($filters['service'] ?? null, fn ($query, $service) => $query->where('service_type', $service))->latest('id')->lazy();

            return [['dataset', 'run_id', 'generated_at', 'service', 'status', 'data_through', 'forecast_month', 'predicted_count', 'planning_lower', 'planning_upper', 'message', 'configuration', 'evaluation'], $runs->flatMap(function (ForecastRun $run) use ($label): array {
                $dataThrough = collect($run->history)->last()['month'] ?? null;
                $points = $run->results['count']['forecast'] ?? [];

                return collect($points ?: [null])->map(fn ($point, $index) => [
                    ($run->configuration['data_source'] ?? null) === 'confirmed_monthly_statistics' ? 'Confirmed monthly parish statistics; saved forecast snapshot' : $label.'; legacy forecast snapshot',
                    $run->id, $run->created_at->toDateTimeString(), $run->service_type ?? 'overall', $run->status, $dataThrough,
                    $point !== null && $dataThrough ? CarbonImmutable::parse($dataThrough.'-01')->addMonths($index + 1)->format('Y-m') : null,
                    $point['estimate'] ?? null, $point['lower'] ?? null, $point['upper'] ?? null,
                    $run->message, json_encode($run->configuration), json_encode($run->evaluation),
                ])->all();
            })];
        }
        if ($type === 'payments') {
            $payments = Payment::query()->with(['appointment', 'originalPayment'])->whereIn('appointment_id', $analytics->appointments($filters['service'] ?? null, $user)->select('appointments.id'))
                ->whereDate('paid_at', '>=', $from)->whereDate('paid_at', '<=', $to)->orderBy('id')->lazy();

            return [['dataset', 'appointment', 'date', 'kind', 'amount', 'method', 'reference', 'receipt_number', 'original_payment_receipt', 'test_transaction'], $payments->map(fn ($payment) => [$label, $payment->appointment->reference_number, $payment->paid_at->toDateTimeString(), $payment->kind, number_format($payment->amount_cents / 100, 2, '.', ''), $payment->method, $payment->reference, $payment->receiptNumber(), $payment->originalPayment?->receiptNumber(), OfficeSettings::demo() || $payment->isSandbox() ? 'Yes' : 'No'])];
        }
        $appointments = $analytics->appointments($filters['service'] ?? null, $user)->with('payments')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($type !== 'outstanding', fn ($q) => $q->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to))
            ->when($type === 'outstanding', fn ($q) => $q->whereIn('status', ['pending', 'confirmed', 'completed']))->orderBy('id')->lazy();
        if ($type === 'outstanding') {
            return [['dataset', 'appointment', 'contact', 'fee', 'net_paid', 'balance'], $appointments->filter(fn (Appointment $a) => $a->balanceCents() > 0)->map(fn (Appointment $a) => [$label, $a->reference_number, $a->client_full_name, $a->fee_cents / 100, $a->netPaidCents() / 100, $a->balanceCents() / 100])];
        }

        return [['dataset', 'appointment', 'contact', 'service', 'status', 'requested_date', 'confirmed_start', 'completed_at'], $appointments->map(fn ($a) => [$label, $a->reference_number, $a->client_full_name, $a->service_type, $a->status, $a->preferred_date->toDateString(), $a->starts_at?->toDateTimeString(), $a->completed_at?->toDateTimeString()])];
    }
}
