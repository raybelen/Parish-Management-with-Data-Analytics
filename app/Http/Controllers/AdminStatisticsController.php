<?php

namespace App\Http\Controllers;

use App\Actions\SaveMonthlyStatistic;
use App\Models\AuditLog;
use App\Models\MonthlyStatistic;
use App\Models\ServiceRecord;
use App\Support\CsvExport;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use App\Support\ParishStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminStatisticsController extends Controller
{
    public function index(Request $request, ParishStatistics $statistics): View|StreamedResponse
    {
        abort_unless(config('office.analytics_enabled'), 404);
        Gate::authorize('reports.view');
        $data = $request->validate(['year' => ['nullable', 'integer', 'between:1900,'.now()->year], 'service' => ['nullable', Rule::in(array_keys(ParishStatistics::SERVICES))], 'export' => ['nullable', 'boolean']]);
        $service = $data['service'] ?? 'baptism';
        $annual = $statistics->annual();
        $latest = collect($annual)->first(fn ($row) => $row['services'][$service]['months'] > 0);
        $year = (int) ($data['year'] ?? $latest['year'] ?? now()->year);
        $rows = MonthlyStatistic::query()->where('source', OfficeSettings::source())->where('service_type', $service)
            ->whereBetween('month', [$year.'-01-01', $year.'-12-01'])->orderBy('month')->get();
        if ($request->boolean('export')) {
            OfficeAudit::record('statistics.exported', null, [], ['year' => $year, 'service' => $service]);

            return CsvExport::download('monthly-statistics-'.$service.'-'.$year.'.csv', ['month', 'service', 'count', 'status', 'reference', 'version'],
                $rows->map(fn ($row) => [$row->month->format('Y-m'), $service, $row->count, $row->status, $row->reference, $row->version]));
        }
        $digital = ServiceRecord::query()->where('source', OfficeSettings::source())->whereNull('archived_at')->where('service_type', $service)
            ->whereBetween('service_date', [$year.'-01-01', $year.'-12-31'])->get(['service_date', 'participants']);
        $suggestions = [];
        foreach ($digital as $record) {
            $month = $record->service_date->format('Y-m');
            $quantity = $service === 'baptism' ? collect($record->participants ?? [])->where('role', 'child')->count() : 1;
            $suggestions[$month] = ($suggestions[$month] ?? 0) + $quantity;
        }

        $indexed = $rows->keyBy(fn ($row) => $row->month->format('Y-m'));
        $annualByYear = collect($annual)->keyBy('year');
        $summary = $annualByYear->get($year)['services'][$service] ?? ['total' => null, 'months' => 0];
        $previous = $annualByYear->get($year - 1)['services'][$service] ?? ['total' => null, 'months' => 0];
        $summary['change'] = $summary['months'] === 12 && $previous['months'] === 12 && $previous['total'] > 0
            ? ($summary['total'] - $previous['total']) / $previous['total'] * 100 : null;
        $monthlyChart = [];
        for ($number = 1; $number <= 12; $number++) {
            $month = CarbonImmutable::create($year, $number, 1);
            $record = $indexed->get($month->format('Y-m'));
            $monthlyChart[] = ['label' => $month->format('M'), 'value' => $record?->status === 'confirmed' && $month->lt(now()->startOfMonth()) ? $record->count : null, 'partial' => false];
        }
        $annualChart = array_map(fn ($row) => [
            'label' => (string) $row['year'], 'value' => $row['services'][$service]['total'],
            'partial' => $row['services'][$service]['months'] > 0 && $row['services'][$service]['months'] < 12,
        ], array_reverse($annual));

        return view('admin.statistics', [
            'year' => $year, 'service' => $service, 'services' => ParishStatistics::SERVICES,
            'rows' => $indexed, 'annual' => $annual, 'summary' => $summary,
            'monthlyChart' => $monthlyChart, 'annualChart' => $annualChart,
            'suggestions' => $suggestions,
            'revisions' => AuditLog::query()->where('entity_type', MonthlyStatistic::class)->whereIn('entity_id', $rows->pluck('id'))->with('actor')->latest('id')->limit(30)->get(),
        ]);
    }

    public function store(Request $request, SaveMonthlyStatistic $save): RedirectResponse
    {
        abort_unless(config('office.analytics_enabled'), 404);
        Gate::authorize('records.manage');
        Gate::authorize('reports.view');
        $data = $request->validate([
            'service_type' => ['required', Rule::in(array_keys(ParishStatistics::SERVICES))],
            'month' => ['required', 'date_format:Y-m', 'after_or_equal:1900-01', 'before:'.now()->format('Y-m')],
            'count' => ['nullable', 'required_if:status,confirmed', 'integer', 'min:0', 'max:1000000'],
            'status' => ['required', Rule::in(['draft', 'confirmed'])],
            'reference' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'max:1000'], 'version' => ['required', 'integer', 'min:0'],
        ]);
        if ($data['status'] === 'confirmed' || MonthlyStatistic::query()->where('source', OfficeSettings::source())->where('service_type', $data['service_type'])->where('month', $data['month'].'-01')->where('status', 'confirmed')->exists()) {
            Gate::authorize('forecasts.manage');
        }
        $save->handle($data);

        return redirect()->route('admin.statistics.index', ['year' => substr($data['month'], 0, 4), 'service' => $data['service_type']])->with('status', 'Monthly total saved. Only confirmed totals enter analytics.');
    }

    public function import(): RedirectResponse
    {
        abort_unless(config('office.analytics_enabled'), 404);
        Gate::authorize('records.manage');
        Gate::authorize('reports.view');
        Gate::authorize('forecasts.manage');
        $code = Artisan::call('analytics:import-history');
        $message = trim(Artisan::output());

        return $code === 0 ? back()->with('status', $message) : back()->withErrors(['import' => $message]);
    }
}
