<?php

namespace App\Http\Controllers;

use App\Models\ParishSetting;
use App\Models\Personnel;
use App\Models\ServiceType;
use App\Models\User;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminSettingsController extends Controller
{
    public function index(): View
    {
        Gate::authorize('settings.manage');
        $abilities = array_unique(array_merge(...array_values(config('office.permissions'))));
        if (! config('office.analytics_enabled')) {
            $abilities = array_values(array_diff($abilities, ['forecasts.manage']));
        }

        return view('admin.settings', [
            'settings' => OfficeSettings::all(), 'services' => ServiceType::query()->orderBy('name')->get(),
            'personnel' => Personnel::query()->with('user')->orderBy('name')->get(),
            'users' => User::query()->where('role', 'priest')->orderBy('name')->get(['id', 'name']),
            'abilities' => $abilities,
            'venues' => config('parish.churches', []),
            'activeVenues' => OfficeSettings::activeVenues(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $rules = [
            'parish_name' => ['required', 'string', 'max:255'], 'opens_at' => ['required', 'date_format:H:i'],
            'closes_at' => ['required', 'date_format:H:i', 'after:opens_at'],
            'operating_days' => ['required', 'array', 'min:1'], 'operating_days.*' => ['integer', 'between:1,7', 'distinct'],
            'active_venues_present' => ['nullable', Rule::in(['1'])],
            'active_venues' => ['nullable', 'array'],
            'active_venues.*' => ['string', 'distinct', Rule::in(config('parish.churches', []))],
        ];
        if (config('office.analytics_enabled')) {
            $rules = [...$rules,
                'analytics_start' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                'missing_months_text' => ['nullable', 'string', 'max:2000'],
                'forecast_horizon' => ['required', 'integer', 'between:1,12'], 'training_months' => ['required', 'integer', 'between:36,120'],
                'minimum_months' => ['required', 'integer', 'between:24,120', 'lte:training_months'],
                'order' => ['required', 'array', 'size:3'], 'order.*' => ['required', 'integer', 'between:0,2'],
                'seasonal_order' => ['required', 'array', 'size:4'],
                'seasonal_order.0' => ['integer', 'between:0,2'], 'seasonal_order.1' => ['integer', 'between:0,1'],
                'seasonal_order.2' => ['integer', 'between:0,2'], 'seasonal_order.3' => ['integer', 'between:2,12'],
            ];
        }
        $data = $request->validate($rules);
        if (config('office.analytics_enabled')) {
            $months = preg_split('/[\s,]+/', trim($data['missing_months_text'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($months as $month) {
                if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                    throw ValidationException::withMessages(['missing_months_text' => 'List unknown months as YYYY-MM, separated by commas.']);
                }
            }
            $data['missing_months'] = array_values(array_unique($months));
            unset($data['missing_months_text']);
            $data['order'] = array_map('intval', $data['order']);
            $data['seasonal_order'] = array_map('intval', $data['seasonal_order']);
        }
        if ($request->boolean('active_venues_present')) {
            $data['active_venues'] = array_values($data['active_venues'] ?? []);
        }
        unset($data['active_venues_present']);
        $data['operating_days'] = array_map('intval', $data['operating_days']);
        DB::transaction(function () use ($data): void {
            $settings = ParishSetting::query()->where('key', 'office')->lockForUpdate()->firstOrFail();
            $before = $settings->value;
            $settings->update(['value' => array_replace($before, $data)]);
            OfficeAudit::record('settings.updated', $settings, $before, $settings->value);
        });
        OfficeSettings::forgetMemoizedValues();

        return back()->with('status', 'Settings saved. Existing confirmed schedules retain their reserved preparation time.');
    }

    public function permissions(Request $request): RedirectResponse
    {
        Gate::authorize('manage-admin-roles');
        $abilities = array_unique(array_merge(...array_values(config('office.permissions'))));
        $data = $request->validate([
            'password' => ['required', 'current_password:web'],
            'permissions' => ['nullable', 'array:admin,staff,priest'],
            'permissions.*' => ['array'], 'permissions.*.*' => ['string', Rule::in($abilities)],
        ]);
        DB::transaction(function () use ($data): void {
            $settings = ParishSetting::query()->where('key', 'office')->lockForUpdate()->firstOrFail();
            $before = $settings->value;
            $permissions = array_replace(['admin' => [], 'staff' => [], 'priest' => []], $data['permissions'] ?? []);
            if (! config('office.analytics_enabled')) {
                foreach (array_keys($permissions) as $role) {
                    $permissions[$role] = array_values(array_diff($permissions[$role], ['forecasts.manage']));
                    $existing = $before['permissions'][$role] ?? config('office.permissions.'.$role, []);
                    if (in_array('forecasts.manage', $existing, true)) {
                        $permissions[$role][] = 'forecasts.manage';
                    }
                }
            }
            $permissions['priest'] = array_values(array_intersect($permissions['priest'], ['appointments.view', 'appointments.approve']));
            $settings->update(['value' => array_replace($before, ['permissions' => $permissions])]);
            OfficeAudit::record('permissions.updated', $settings, ['permissions' => $before['permissions'] ?? config('office.permissions')], ['permissions' => $permissions]);
        });
        OfficeSettings::forgetMemoizedValues();

        return back()->with('status', 'Permissions updated. Role assignment remains restricted to super administrators.');
    }
}
