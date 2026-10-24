<?php

namespace App\Console\Commands;

use App\Actions\GenerateForecast;
use App\Support\ParishStatistics;
use Illuminate\Console\Command;

class GenerateParishForecasts extends Command
{
    protected $signature = 'analytics:forecast {--service=baptism : baptism, wedding or funeral} {--all : Generate the three supported service forecasts}';

    protected $description = 'Generate SARIMA forecasts from confirmed monthly parish statistics';

    public function handle(GenerateForecast $generate): int
    {
        if (! config('office.analytics_enabled')) {
            $this->error('Analytics is disabled.');

            return self::FAILURE;
        }
        $services = $this->option('all') ? ParishStatistics::FORECAST_SERVICES : [$this->option('service')];
        if (array_diff($services, ParishStatistics::FORECAST_SERVICES)) {
            $this->error('Choose baptism, wedding or funeral.');

            return self::FAILURE;
        }
        $failed = false;
        foreach ($services as $service) {
            $run = $generate->handle($service);
            $this->info($service.': '.$run->status.' - '.$run->message);
            $failed = $failed || ! in_array($run->status, ['ready', 'unreliable'], true);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
