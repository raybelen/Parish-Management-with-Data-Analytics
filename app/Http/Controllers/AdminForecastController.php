<?php

namespace App\Http\Controllers;

use App\Actions\GenerateForecast;
use App\Models\ForecastRun;
use App\Support\OfficeSettings;
use App\Support\ParishStatistics;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminForecastController extends Controller
{
    public function index(Request $request, ParishStatistics $statistics): View
    {
        abort_unless(config('office.analytics_enabled'), 404);
        Gate::authorize('forecasts.manage');
        Gate::authorize('finance.view');
        $data = $request->validate(['service' => ['nullable', Rule::in(ParishStatistics::FORECAST_SERVICES)]]);
        $service = $data['service'] ?? 'baptism';
        $history = $statistics->history($service);
        $runs = ForecastRun::query()->where('source', OfficeSettings::source())->where('service_type', $service)->latest('id')->limit(10)->get();
        $run = $runs->first();
        $stale = $run && (($run->configuration['history_hash'] ?? '') !== $statistics->fingerprint($history)
            || ($run->configuration['model_version'] ?? '') !== config('forecast.version'));

        return view('admin.forecasts', [
            'history' => $history, 'run' => $run, 'runs' => $runs, 'stale' => $stale, 'service' => $service,
            'services' => array_intersect_key(ParishStatistics::SERVICES, array_flip(ParishStatistics::FORECAST_SERVICES)),
            'cutoff' => collect($history)->last()['month'] ?? null,
            'latestCompleteMonth' => now()->startOfMonth()->subMonth()->format('Y-m'),
        ]);
    }

    public function store(Request $request, GenerateForecast $generate): RedirectResponse
    {
        abort_unless(config('office.analytics_enabled'), 404);
        Gate::authorize('forecasts.manage');
        Gate::authorize('finance.view');
        $data = $request->validate(['service' => ['nullable', Rule::in(ParishStatistics::FORECAST_SERVICES)]]);
        $service = $data['service'] ?? 'baptism';
        $run = $generate->handle($service);

        $message = match ($run->status) {
            'ready' => 'Estimates updated.',
            'unreliable' => 'Estimates updated. Please read the planning caution below.',
            'insufficient_data' => 'More complete monthly records are needed before estimates can be prepared.',
            default => 'Estimates could not be updated. Please try again or contact your system administrator.',
        };

        return redirect()->route('admin.forecasts.index', ['service' => $service])->with('status', $message);
    }
}
