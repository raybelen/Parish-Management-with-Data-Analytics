<?php

namespace App\Console\Commands;

use App\Actions\GenerateForecast;
use App\Models\ServiceType;
use Illuminate\Console\Command;

class GenerateParishForecasts extends Command
{
    protected $signature = 'analytics:forecast {--service= : Service code; omit for overall} {--all : Generate overall and each service}';

    protected $description = 'Generate and store SARIMA forecasts from eligible monthly operational data';

    public function handle(GenerateForecast $generate): int
    {
        if (! config('office.analytics_enabled')) {
            $this->error('Analytics and SARIMA forecasting are temporarily disabled.');

            return self::FAILURE;
        }

        $service = $this->option('service');
        if ($service && ! ServiceType::query()->where('slug', $service)->exists()) {
            $this->error('Unknown service code.');

            return self::FAILURE;
        }
        foreach ($this->option('all') ? [null, ...ServiceType::query()->pluck('slug')->all()] : [$service] as $code) {
            $run = $generate->handle($code);
            $this->info(($code ?? 'Overall').': '.$run->status.' — '.$run->message);
        }

        return self::SUCCESS;
    }
}
