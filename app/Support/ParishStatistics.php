<?php

namespace App\Support;

use App\Models\MonthlyStatistic;
use Illuminate\Support\Facades\Schema;

class ParishStatistics
{
    public const SERVICES = ['baptism' => 'Baptisms (people)', 'wedding' => 'Weddings (couples)', 'funeral' => 'Funerals (Masses)', 'confirmation' => 'Confirmations (people)', 'first_communion' => 'First Communicants (people)'];

    public const FORECAST_SERVICES = ['baptism', 'wedding', 'funeral'];

    /** @return list<array{month: string, count: ?int}> */
    public function history(string $service): array
    {
        if (! Schema::hasTable('monthly_statistics')) {
            return [];
        }
        $records = MonthlyStatistic::query()->where('source', OfficeSettings::source())->where('service_type', $service)
            ->where('month', '<', now()->startOfMonth()->toDateString())->orderBy('month')->get();
        $confirmed = $records->where('status', 'confirmed')->whereNotNull('count');
        if ($confirmed->isEmpty()) {
            return [];
        }
        $indexed = $records->keyBy(fn ($record) => $record->month->format('Y-m'));
        $history = [];
        for ($month = $confirmed->first()->month; $month <= $confirmed->last()->month; $month = $month->addMonth()) {
            $record = $indexed->get($month->format('Y-m'));
            $history[] = ['month' => $month->format('Y-m'), 'count' => $record?->status === 'confirmed' ? $record->count : null];
        }

        return $history;
    }

    /** @param list<array{month: string, count: ?int}> $history */
    public function fingerprint(array $history): string
    {
        return hash('sha256', json_encode($history, JSON_THROW_ON_ERROR));
    }

    /** @return list<array{year: int, services: array<string, array{total: ?int, months: int}>}> */
    public function annual(): array
    {
        if (! Schema::hasTable('monthly_statistics')) {
            return [];
        }
        $records = MonthlyStatistic::query()->where('source', OfficeSettings::source())->where('status', 'confirmed')
            ->whereNotNull('count')->where('month', '<', now()->startOfMonth()->toDateString())->orderBy('month')->get()->groupBy(fn ($record) => $record->month->year);
        $years = [];
        foreach ($records as $year => $rows) {
            $services = [];
            foreach (self::SERVICES as $service => $label) {
                $matches = $rows->where('service_type', $service);
                $services[$service] = ['total' => $matches->isEmpty() ? null : (int) $matches->sum('count'), 'months' => $matches->count()];
            }
            $years[] = ['year' => (int) $year, 'services' => $services];
        }

        return array_reverse($years);
    }
}
