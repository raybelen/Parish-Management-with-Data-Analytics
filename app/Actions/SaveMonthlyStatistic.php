<?php

namespace App\Actions;

use App\Models\MonthlyStatistic;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveMonthlyStatistic
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): MonthlyStatistic
    {
        $key = ['source' => OfficeSettings::source(), 'service_type' => $data['service_type'], 'month' => $data['month'].'-01'];

        return Cache::lock('monthly-statistic:'.implode(':', $key), 15)->block(3, function () use ($key, $data): MonthlyStatistic {
            return DB::transaction(function () use ($key, $data): MonthlyStatistic {
                $record = MonthlyStatistic::query()->where($key)->lockForUpdate()->first();
                if ($record?->status === 'confirmed' || $data['status'] === 'confirmed') {
                    Gate::authorize('forecasts.manage');
                }
                if (($record?->version ?? 0) !== (int) $data['version']) {
                    throw ValidationException::withMessages(['version' => 'This month changed while you were editing. Reload and review the latest total.']);
                }
                $before = $record?->toArray() ?? [];
                $record ??= new MonthlyStatistic($key);
                $confirmed = $data['status'] === 'confirmed';
                $record->fill([
                    'count' => $data['count'] ?? null, 'status' => $data['status'],
                    'reference' => $data['reference'], 'notes' => $data['notes'] ?? null,
                    'version' => ($record->version ?? 0) + 1,
                    'confirmed_by' => $confirmed ? auth()->id() : null,
                    'confirmed_at' => $confirmed ? now() : null,
                ])->save();
                OfficeAudit::record('statistics.saved', $record, $before, $record->toArray(), $data['reason']);

                return $record;
            });
        });
    }
}
