<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ParishSetting;
use App\Models\ScheduleBlock;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminScheduleBlockController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'church' => ['required_without:personnel_id', 'nullable', Rule::in(config('parish.churches'))],
            'personnel_id' => ['nullable', 'integer', 'exists:personnel,id'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'], 'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
        ]);
        DB::transaction(function () use ($data): void {
            ParishSetting::query()->where('key', 'office')->lockForUpdate()->firstOrFail();
            $conflict = Appointment::query()->where('source', OfficeSettings::source())->where('status', 'confirmed')
                ->where('starts_at', '<', str_replace('T', ' ', $data['ends_at']))->where('reserved_until', '>', str_replace('T', ' ', $data['starts_at']))
                ->where(function ($q) use ($data): void {
                    $q->whereRaw('1 = 0');
                    if (! empty($data['church'])) {
                        $q->orWhere('confirmed_church', $data['church']);
                    }
                    if (! empty($data['personnel_id'])) {
                        $q->orWhere('personnel_id', $data['personnel_id']);
                    }
                })->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['starts_at' => 'Reschedule the conflicting confirmed appointment before blocking this time.']);
            }
            $block = ScheduleBlock::create($data);
            OfficeAudit::record('schedule.blocked', $block, [], $block->toArray());
        });

        return back()->with('status', 'Unavailable time saved.');
    }

    public function destroy(Request $request, ScheduleBlock $block): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($block, $data): void {
            OfficeAudit::record('schedule.unblocked', $block, $block->toArray(), [], $data['reason']);
            $block->delete();
        });

        return back()->with('status', 'Unavailable time removed.');
    }
}
