<?php

namespace App\Support;

use App\Models\MonthlyStatistic;
use Carbon\CarbonImmutable;

class ParishInsights
{
    /**
     * Descriptive measures use only verified, completed months of the selected service.
     * Historical screening uses the five preceding calendar years, never later records.
     *
     * @return array{expected: int, verified: int, missing: list<string>, drafts: int, through: ?string, period: string, comparison: ?array{current: int, previous: int, difference: int, percent: ?float}, average: ?float, zero_months: int, peak: ?array{labels: list<string>, count: int, share: float}, seasonal: list<array{label: string, value: ?float, partial: bool}>, seasonal_peak: list<string>, anomalies: list<array{month: string, actual: int, typical: float, lower: float, upper: float, direction: string}>, screened: int, previous_months: array<int, int>, recommendations: list<array{title: string, text: string, review: bool}>}
     */
    public function forService(string $service, int $year): array
    {
        $today = CarbonImmutable::now();
        $expected = $year < $today->year ? 12 : $today->month - 1;
        $monthly = in_array($service, ParishStatistics::FORECAST_SERVICES, true);
        $records = MonthlyStatistic::query()->where('source', OfficeSettings::source())->where('service_type', $service)
            ->whereBetween('month', [($year - 5).'-01-01', $year.'-12-01'])
            ->where('month', '<', $today->startOfMonth()->toDateString())
            ->orderBy('month')->get(['month', 'count', 'status']);
        $verified = $records->filter(fn ($row) => $row->status === 'confirmed' && $row->count !== null);
        $current = $verified->filter(fn ($row) => $row->month->year === $year)->keyBy(fn ($row) => $row->month->month);
        $previous = $verified->filter(fn ($row) => $row->month->year === $year - 1)->keyBy(fn ($row) => $row->month->month);
        $missing = [];
        $priorComplete = true;
        for ($month = 1; $month <= $expected; $month++) {
            if (! $current->has($month)) {
                $missing[] = CarbonImmutable::create($year, $month, 1)->format('F');
            }
            $priorComplete = $priorComplete && $previous->has($month);
        }
        $period = $expected === 0 ? 'No completed months yet' : ($expected === 1 ? 'January' : 'January–'.CarbonImmutable::create($year, $expected, 1)->format('F'));
        $comparison = null;
        if ($expected > 0 && $missing === [] && $priorComplete && ($monthly || $expected === 12)) {
            $currentTotal = (int) $current->sum('count');
            $previousTotal = (int) $previous->filter(fn ($row) => $row->month->month <= $expected)->sum('count');
            $comparison = ['current' => $currentTotal, 'previous' => $previousTotal, 'difference' => $currentTotal - $previousTotal,
                'percent' => $previousTotal > 0 ? ($currentTotal - $previousTotal) / $previousTotal * 100 : null];
        }

        $peak = null;
        if ($current->sum('count') > 0) {
            $peakCount = (int) $current->max('count');
            $peak = ['labels' => $current->where('count', $peakCount)->map(fn ($row) => $row->month->format('F'))->values()->all(),
                'count' => $peakCount, 'share' => $peakCount / $current->sum('count') * 100];
        }

        $seasonal = $anomalies = [];
        $screened = 0;
        if ($monthly) {
            for ($month = 1; $month <= 12; $month++) {
                $date = CarbonImmutable::create($year, $month, 1);
                $sample = $verified->filter(fn ($row) => $row->month->year < $year && $row->month->month === $month)->pluck('count');
                $typical = $sample->count() === 5 ? (float) $sample->median() : null;
                $seasonal[] = ['label' => $date->format('M'), 'value' => $typical, 'partial' => false];
                if ($typical === null || ! $current->has($month)) {
                    continue;
                }
                $screened++;
                $mad = (float) $sample->map(fn ($count) => abs($count - $typical))->median();
                $radius = max(3, 3.5 * $mad / 0.6745);
                $actual = $current->get($month)->count;
                if (abs($actual - $typical) > $radius) {
                    $anomalies[] = ['month' => $date->format('F'), 'actual' => $actual, 'typical' => $typical,
                        'lower' => max(0, $typical - $radius), 'upper' => $typical + $radius,
                        'direction' => $actual > $typical ? 'Higher' : 'Lower'];
                }
            }
        }
        $seasonalPeak = [];
        $supported = collect($seasonal)->whereNotNull('value');
        if ($supported->count() === 12 && $supported->max('value') > $supported->min('value')) {
            $seasonalPeak = $supported->where('value', $supported->max('value'))->pluck('label')->all();
        }

        $recommendations = [];
        if ($missing !== []) {
            $recommendations[] = ['title' => 'Complete the monthly records', 'text' => 'Verify '.implode(', ', $missing).' before making a full '.$period.' comparison. Missing counts are not treated as zero.', 'review' => true];
        }
        if ($anomalies !== []) {
            $recommendations[] = ['title' => 'Review unusual counts', 'text' => 'Check '.implode(', ', array_column($anomalies, 'month')).' against the register. If correct, note any group service or schedule change; keep the original count.', 'review' => true];
        }
        if ($seasonalPeak !== []) {
            $recommendations[] = ['title' => 'Prepare for historically busier months', 'text' => implode(', ', $seasonalPeak).' had the highest typical activity in '.($year - 5).'–'.($year - 1).'. Review staff and document-processing capacity before those months; confirm plans against bookings and the latest forecast.', 'review' => false];
        }
        if ($comparison !== null && $comparison['difference'] !== 0) {
            $recommendations[] = ['title' => 'Review service capacity', 'text' => 'Recorded activity is '.abs($comparison['difference']).' '.($comparison['difference'] > 0 ? 'higher' : 'lower').' than the same period in '.($year - 1).'. Discuss scheduling and service availability with parish staff before adjusting resources; counts alone do not explain the cause.', 'review' => false];
        }
        if (! $monthly) {
            $recommendations[] = ['title' => 'Plan group ceremonies from the parish calendar', 'text' => 'Confirm group dates and participant lists with staff. Annual totals describe participation, while changes may reflect when a group ceremony was scheduled.', 'review' => false];
        } elseif ($recommendations === []) {
            $recommendations[] = ['title' => 'Use the register and upcoming bookings together', 'text' => 'Continue verifying each completed month. Use confirmed appointments and the latest forecast to plan office support; historical counts are not a staffing target.', 'review' => false];
        }

        return [
            'expected' => $expected, 'verified' => $current->count(), 'missing' => $missing,
            'drafts' => $records->filter(fn ($row) => $row->month->year === $year && $row->status === 'draft')->count(),
            'through' => $current->last()?->month->format('F Y'), 'period' => $period, 'comparison' => $comparison,
            'average' => $current->isEmpty() ? null : (float) $current->avg('count'), 'zero_months' => $current->where('count', 0)->count(),
            'peak' => $peak, 'seasonal' => $seasonal, 'seasonal_peak' => $seasonalPeak, 'anomalies' => $anomalies, 'screened' => $screened,
            'previous_months' => $previous->map(fn ($row) => $row->count)->all(), 'recommendations' => $recommendations,
        ];
    }
}
