<?php

namespace App\Http\Controllers;

use App\Actions\ImportServiceRecords;
use App\Models\Appointment;
use App\Models\ForecastRun;
use App\Models\Payment;
use App\Models\ServiceRecord;
use App\Models\ServiceType;
use App\Support\AnalyticsData;
use App\Support\CsvExport;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['nullable', Rule::in($reportTypes)],
            'status' => ['nullable', Rule::in(Appointment::STATUSES)], 'service' => ['nullable', 'string', 'exists:service_types,slug'],
            'export' => ['nullable', 'boolean'],
        ]);
        $from = $data['from'] ?? now()->startOfYear()->toDateString();
        $to = $data['to'] ?? now()->toDateString();
        $type = $data['type'] ?? 'appointments';
        if ($type === 'appointments' && $request->boolean('export')) {
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
            [$columns, $rows] = $this->rows($type, $data, $from, $to, $analytics);
            OfficeAudit::record('report.exported', null, [], ['type' => $type, 'from' => $from, 'to' => $to, 'source' => OfficeSettings::source()]);

            return CsvExport::download((OfficeSettings::demo() ? 'DEMO-' : '').$type.'-'.$from.'-'.$to.'.csv', $columns, $rows);
        }

        return view('admin.reports', [
            'from' => $from, 'to' => $to,
            'totals' => $request->user()->can('finance.view') ? $analytics->totals($from, $to) : null,
            'services' => ServiceType::query()->orderBy('name')->get(),
        ]);
    }

    /** @param array<string, mixed> $filters
     * @return array{array<int, string>, iterable<array<array-key, mixed>>}
     */
    private function rows(string $type, array $filters, string $from, string $to, AnalyticsData $analytics): array
    {
        $label = OfficeSettings::demo() ? 'DEMO - simulated data' : 'Operational data';
        if ($type === 'records') {
            $records = ServiceRecord::query()->whereIn('source', ['archival', OfficeSettings::source()])->whereBetween('service_date', [$from, $to])
                ->when($filters['service'] ?? null, fn ($q, $service) => $q->where('service_type', $service))->orderBy('id')->lazy();

            return [[...ImportServiceRecords::COLUMNS, 'source', 'archived_at'], $records->map(fn ($record) => [...$record->only(ImportServiceRecords::COLUMNS), 'source' => $record->source, 'archived_at' => $record->archived_at?->toDateTimeString()])];
        }
        if ($type === 'monthly') {
            return [['dataset', 'month', 'completed_services', 'net_collections_for_completed_services'], collect($analytics->monthlyReport($from, $to, $filters['service'] ?? null))->map(fn ($row) => [$label, ...array_values($row)])];
        }
        if ($type === 'forecast') {
            $runs = ForecastRun::query()->where('source', OfficeSettings::source())->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
                ->when($filters['service'] ?? null, fn ($query, $service) => $query->where('service_type', $service))->latest('id')->lazy();

            return [['dataset', 'generated_at', 'service', 'status', 'data_through', 'configuration', 'results', 'evaluation'], $runs->map(fn ($run) => [
                ($run->configuration['data_source'] ?? null) === 'confirmed_monthly_statistics' ? 'Confirmed monthly parish statistics; saved forecast snapshot' : $label.'; legacy forecast snapshot',
                $run->created_at->toDateTimeString(), $run->service_type ?? 'overall', $run->status,
                collect($run->history)->last()['month'] ?? null, json_encode($run->configuration), json_encode($run->results), json_encode($run->evaluation),
            ])];
        }
        if ($type === 'payments') {
            $payments = Payment::query()->with('appointment')->whereHas('appointment', fn ($q) => $q->where('source', OfficeSettings::source())->when($filters['service'] ?? null, fn ($q, $service) => $q->where('service_type', $service)))
                ->whereDate('paid_at', '>=', $from)->whereDate('paid_at', '<=', $to)->orderBy('id')->lazy();

            return [['dataset', 'appointment', 'date', 'kind', 'amount', 'method', 'reference'], $payments->map(fn ($payment) => [$label, $payment->appointment->reference_number, $payment->paid_at->toDateTimeString(), $payment->kind, number_format($payment->amount_cents / 100, 2, '.', ''), $payment->method, $payment->reference])];
        }
        $appointments = $analytics->appointments($filters['service'] ?? null)->with('payments')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($type !== 'outstanding', fn ($q) => $q->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to))
            ->when($type === 'outstanding', fn ($q) => $q->whereIn('status', ['pending', 'confirmed', 'completed']))->orderBy('id')->lazy();
        if ($type === 'outstanding') {
            return [['dataset', 'appointment', 'contact', 'fee', 'net_paid', 'balance'], $appointments->filter(fn (Appointment $a) => $a->balanceCents() > 0)->map(fn (Appointment $a) => [$label, $a->reference_number, $a->client_full_name, $a->fee_cents / 100, $a->netPaidCents() / 100, $a->balanceCents() / 100])];
        }

        return [['dataset', 'appointment', 'contact', 'service', 'status', 'requested_date', 'confirmed_start', 'completed_at'], $appointments->map(fn ($a) => [$label, $a->reference_number, $a->client_full_name, $a->service_type, $a->status, $a->preferred_date->toDateString(), $a->starts_at?->toDateTimeString(), $a->completed_at?->toDateTimeString()])];
    }
}
