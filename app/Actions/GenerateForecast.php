<?php

namespace App\Actions;

use App\Models\ForecastRun;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use App\Support\ParishStatistics;
use App\Support\SarimaEngine;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Throwable;

class GenerateForecast
{
    public function __construct(private ParishStatistics $statistics, private SarimaEngine $engine) {}

    public function handle(?string $service = null): ForecastRun
    {
        $service ??= 'baptism';
        if (! in_array($service, ParishStatistics::FORECAST_SERVICES, true)) {
            throw new InvalidArgumentException('This service supports historical analytics only.');
        }

        return Cache::lock('forecast:'.OfficeSettings::source().':'.$service, (int) config('office.forecast_timeout') + 30)->block(2, function () use ($service): ForecastRun {
            $history = $this->statistics->history($service);
            $configuration = [...config('forecast.services.'.$service), 'forecast_horizon' => 6, 'minimum_months' => 72,
                'model_version' => config('forecast.version'), 'history_hash' => $this->statistics->fingerprint($history),
                'data_source' => 'confirmed_monthly_statistics'];
            $run = ForecastRun::create([
                'source' => OfficeSettings::source(), 'service_type' => $service, 'status' => 'insufficient_data',
                'message' => 'At least 72 consecutive confirmed monthly totals are required. Enter or confirm the missing parish statistics.',
                'configuration' => $configuration, 'history' => $history, 'generated_by' => auth()->id(),
            ]);
            if (collect($history)->contains(fn ($row) => $row['count'] === null)) {
                $run->update(['message' => 'There are unconfirmed or missing months inside the history. Confirm those totals before forecasting; unknown months are not zero.']);
            } elseif (count($history) >= 72) {
                try {
                    $output = $this->engine->forecast($history, $configuration);
                    $hasForecast = isset($output['count']['forecast']);
                    $passed = $output['count']['evaluation']['outperformed_baselines'] ?? false;
                    $run->update([
                        'status' => $hasForecast ? ($passed ? 'ready' : 'unreliable') : 'unavailable',
                        'message' => $hasForecast
                            ? ($passed ? 'SARIMA outperformed both comparison baselines in the rolling test. Estimates remain uncertain.' : 'Planning estimate: SARIMA did not outperform both comparison baselines. Review the error and planning range before use.')
                            : ($output['count']['error'] ?? 'Forecast unavailable for this series.'),
                        'results' => $output, 'evaluation' => $output['count']['evaluation'] ?? null,
                    ]);
                } catch (Throwable $exception) {
                    report($exception);
                    $run->update(['status' => 'unavailable', 'message' => 'Forecast generation failed. Check the Python runtime and server logs, then retry.']);
                }
            }
            OfficeAudit::record('forecast.generated', $run, [], ['status' => $run->status, 'source' => $run->source, 'service_type' => $service]);

            return $run;
        });
    }
}
