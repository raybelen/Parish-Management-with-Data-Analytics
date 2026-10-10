<?php

namespace App\Actions;

use App\Models\ForecastRun;
use App\Support\AnalyticsData;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use App\Support\SarimaEngine;
use Illuminate\Support\Facades\Cache;
use Throwable;

class GenerateForecast
{
    public function __construct(private AnalyticsData $analytics, private SarimaEngine $engine) {}

    public function handle(?string $service = null): ForecastRun
    {
        return Cache::lock('forecast:'.OfficeSettings::source().':'.($service ?? 'overall'), 70)->block(2, function () use ($service): ForecastRun {
            $settings = OfficeSettings::all();
            $history = $this->analytics->monthly($service);
            $configuration = array_intersect_key($settings, array_flip(['forecast_horizon', 'training_months', 'minimum_months', 'order', 'seasonal_order', 'analytics_start', 'missing_months']));
            $run = ForecastRun::create([
                'source' => OfficeSettings::source(), 'service_type' => $service, 'status' => 'insufficient_data',
                'message' => 'Insufficient complete monthly history. Confirm the analytics start date and collect eligible completed appointments.',
                'configuration' => $configuration, 'history' => $history, 'generated_by' => auth()->id(),
            ]);
            $minimum = max($settings['minimum_months'], 2 * $settings['seasonal_order'][3] + min(6, $settings['forecast_horizon']));
            if (count($history) >= $minimum && ! collect($history)->contains(fn ($row) => $row['count'] === null || $row['revenue'] === null)) {
                try {
                    $output = $this->engine->forecast($history, $configuration);
                    $hasForecast = isset($output['count']['forecast']) || isset($output['revenue']['forecast']);
                    $reliable = ($output['count']['evaluation']['reliable'] ?? false) && ($output['revenue']['evaluation']['reliable'] ?? false);
                    $run->update([
                        'status' => $hasForecast ? ($reliable ? 'ready' : 'unreliable') : 'unavailable',
                        'message' => $hasForecast ? ($reliable ? 'Estimates only; actual outcomes may differ.' : 'Use caution: one or more series failed validation or did not outperform the seasonal baseline.') : 'The series cannot support reliable seasonal estimation.',
                        'results' => $output,
                        'evaluation' => ['count' => $output['count']['evaluation'] ?? null, 'revenue' => $output['revenue']['evaluation'] ?? null],
                    ]);
                } catch (Throwable $exception) {
                    report($exception);
                    $run->update(['status' => 'unavailable', 'message' => 'Forecasting could not run. Check the Python runtime, installed statsmodels package, and server logs.']);
                }
            } elseif (collect($history)->contains(fn ($row) => $row['count'] === null)) {
                $run->update(['message' => 'Missing months are marked unknown. Resolve missing records before forecasting; unknown months are not treated as zero.']);
            }
            OfficeAudit::record('forecast.generated', $run, [], ['status' => $run->status, 'source' => $run->source, 'service_type' => $service]);

            return $run;
        });
    }
}
