<?php

namespace App\Http\Controllers;

use App\Actions\GenerateForecast;
use App\Models\ForecastRun;
use App\Models\ServiceType;
use App\Support\AnalyticsData;
use App\Support\OfficeSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminForecastController extends Controller
{
    public function index(Request $request, AnalyticsData $analytics): View
    {
        abort_unless(config('office.analytics_enabled'), 404);
        Gate::authorize('forecasts.manage');
        Gate::authorize('finance.view');
        $data = $request->validate(['service' => ['nullable', 'string', 'exists:service_types,slug']]);
        $service = $data['service'] ?? null;

        return view('admin.forecasts', [
            'history' => $analytics->monthly($service),
            'run' => ForecastRun::query()->where('source', OfficeSettings::source())->where('service_type', $service)->latest('id')->first(),
            'services' => ServiceType::query()->orderBy('name')->get(),
            'runs' => ForecastRun::query()->where('source', OfficeSettings::source())->latest('id')->limit(10)->get(),
            'settings' => OfficeSettings::all(),
        ]);
    }

    public function store(Request $request, GenerateForecast $generate): RedirectResponse
    {
        abort_unless(config('office.analytics_enabled'), 404);
        Gate::authorize('forecasts.manage');
        Gate::authorize('finance.view');
        $data = $request->validate(['service' => ['nullable', 'string', 'exists:service_types,slug']]);
        $run = $generate->handle($data['service'] ?? null);

        return redirect()->route('admin.forecasts.index', ['service' => $data['service'] ?? null])->with('status', $run->message);
    }
}
