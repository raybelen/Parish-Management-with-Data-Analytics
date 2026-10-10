<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class SarimaEngine
{
    /** @param list<array<string, mixed>> $history
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    public function forecast(array $history, array $configuration): array
    {
        $result = Process::timeout(config('office.forecast_timeout'))
            ->input(json_encode(['history' => $history, 'configuration' => $configuration], JSON_THROW_ON_ERROR))
            ->run([config('office.python'), app_path('Support/sarima.py')]);
        if (! $result->successful()) {
            throw new RuntimeException('The forecasting runtime is unavailable or failed. Check the configured Python environment and statsmodels installation.');
        }
        $output = json_decode($result->output(), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($output) || ! isset($output['count'], $output['revenue'])) {
            throw new RuntimeException('The forecasting runtime returned an invalid result.');
        }

        return $output;
    }
}
