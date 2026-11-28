<?php

namespace App\Support;

use App\Models\ForecastRun;
use Carbon\CarbonImmutable;

class ForecastReview
{
    /**
     * @param  list<array{month: string, count: ?int}>  $history
     * @return list<array{month: string, generated: string, horizon: int, estimate: int, actual: int, difference: int, lower: int, upper: int, covered: bool, caution: bool}>
     */
    public function forService(string $service, array $history): array
    {
        $start = now()->startOfMonth()->subMonths(12)->format('Y-m');
        $end = now()->startOfMonth()->format('Y-m');
        $actuals = collect($history)->filter(fn (array $row): bool => $row['count'] !== null && $row['month'] >= $start && $row['month'] < $end)->keyBy('month');
        if ($actuals->isEmpty()) {
            return [];
        }

        $rows = [];
        $runs = ForecastRun::query()->where('source', OfficeSettings::source())->where('service_type', $service)
            ->whereIn('status', ['ready', 'unreliable'])->where('created_at', '<', now()->startOfMonth())
            ->orderByDesc('created_at')->orderByDesc('id')->lazy(100);
        foreach ($runs as $run) {
            $cutoff = collect($run->history)->last()['month'] ?? null;
            if (! $cutoff) {
                continue;
            }
            $firstMonth = CarbonImmutable::parse($cutoff.'-01')->addMonth();
            foreach ($run->results['count']['forecast'] ?? [] as $index => $point) {
                $month = $firstMonth->addMonths($index);
                $key = $month->format('Y-m');
                if (isset($rows[$key]) || ! $actuals->has($key) || $run->created_at->greaterThanOrEqualTo($month) || $run->updated_at->greaterThanOrEqualTo($month)) {
                    continue;
                }
                $actual = $actuals[$key]['count'];
                $estimate = (int) round($point['estimate']);
                $lower = (int) floor($point['lower']);
                $upper = (int) ceil($point['upper']);
                $rows[$key] = ['month' => $month->format('F Y'), 'generated' => $run->created_at->format('M j, Y'),
                    'horizon' => $index + 1, 'estimate' => $estimate, 'actual' => $actual, 'difference' => $actual - $estimate,
                    'lower' => $lower, 'upper' => $upper, 'covered' => $actual >= $lower && $actual <= $upper,
                    'caution' => $run->status === 'unreliable'];
            }
            if (count($rows) === $actuals->count()) {
                break;
            }
        }
        krsort($rows);

        return array_values($rows);
    }
}
