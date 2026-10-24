<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\OfficeNotification;
use App\Support\AnalyticsData;
use App\Support\OfficeAccess;
use App\Support\OfficeSettings;
use App\Support\ParishStatistics;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request, AnalyticsData $analytics, ParishStatistics $statistics): View
    {
        $query = OfficeAccess::appointments($request->user());
        if (! $request->user()->can('appointments.view')) {
            $query->whereRaw('1 = 0');
        }

        return view('admin.dashboard', [
            'counts' => (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'services' => (clone $query)->selectRaw('service_type, count(*) as total')->groupBy('service_type')->pluck('total', 'service_type'),
            'upcoming' => (clone $query)->with('personnel')->where('status', 'confirmed')->where('starts_at', '>=', now())->orderBy('starts_at')->limit(8)->get(),
            'totals' => $request->user()->can('finance.view') ? $analytics->totals(now()->startOfMonth()->toDateString(), now()->toDateString()) : null,
            'annualStatistics' => config('office.analytics_enabled') && $request->user()->can('reports.view') ? $statistics->annual() : [],
            'notifications' => $request->user()->can('notifications.manage') ? OfficeNotification::query()->whereHas('appointment', fn ($q) => $q->where('source', OfficeSettings::source()))->whereNull('read_at')->latest('id')->limit(8)->get() : collect(),
            'activity' => $request->user()->can('audit.view') ? AuditLog::query()->with('actor')->latest('id')->limit(6)->get() : collect(),
        ]);
    }
}
