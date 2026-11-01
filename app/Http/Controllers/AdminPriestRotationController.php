<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ParishSetting;
use App\Models\Personnel;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use App\Support\PriestRotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminPriestRotationController extends Controller
{
    public function update(Request $request, PriestRotation $rotation): RedirectResponse
    {
        Gate::authorize('schedules.manage');
        $data = $request->validate([
            'priests' => ['required', 'array:1,2,3', 'size:3'],
            'priests.1' => ['required', 'integer', 'distinct', Rule::exists('personnel', 'id')->where('active', true)],
            'priests.2' => ['required', 'integer', 'distinct', Rule::exists('personnel', 'id')->where('active', true)],
            'priests.3' => ['required', 'integer', 'distinct', Rule::exists('personnel', 'id')->where('active', true)],
        ]);

        DB::transaction(function () use ($data, $rotation): void {
            $settings = ParishSetting::query()->where('key', 'office')->lockForUpdate()->firstOrFail();
            Personnel::query()->whereNotNull('rotation_position')->update(['rotation_position' => null]);
            foreach ($data['priests'] as $position => $personnelId) {
                Personnel::query()->whereKey($personnelId)->update(['rotation_position' => (int) $position]);
            }
            OfficeSettings::forgetMemoizedValues();
            $anchor = Appointment::query()->whereIn('status', ['pending', 'confirmed'])->where('starts_at', '>=', now())
                ->orderBy('starts_at')->orderBy('id')->lockForUpdate()->first();
            if ($anchor === null) {
                throw ValidationException::withMessages(['priests' => 'A future appointment is required to establish the rotation starting point.']);
            }
            $anchor->forceFill(['personnel_id' => (int) $data['priests'][1]])->save();
            $before = $settings->value;
            $settings->update(['value' => array_replace($before, [
                'priest_rotation_anchor_date' => $anchor->starts_at->toDateString(),
                'priest_rotation_anchor_position' => 1,
            ])]);
            OfficeSettings::forgetMemoizedValues();
            Appointment::query()->whereIn('status', ['pending', 'confirmed'])->where('starts_at', '>=', now())
                ->whereKeyNot($anchor->id)->orderBy('starts_at')->orderBy('id')->get()
                ->each(fn (Appointment $appointment) => $rotation->assignAppointment($appointment));
            OfficeAudit::record('priest.rotation_updated', $settings, $before, $settings->value);
        });

        return back()->with('status', 'Priest rotation saved and future appointments assigned from the next appointment.');
    }
}
