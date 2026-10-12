<?php

namespace App\Http\Controllers;

use App\Actions\ManageSchedule;
use App\Http\Requests\SaveScheduleRequest;
use App\Models\Schedule;
use App\Support\OfficeAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminScheduleController extends Controller
{
    public function store(SaveScheduleRequest $request, ManageSchedule $manage): JsonResponse
    {
        $schedules = $manage->create($request->validated());

        return response()->json(['message' => count($schedules).' schedule(s) created.'], 201);
    }

    public function update(SaveScheduleRequest $request, Schedule $schedule, ManageSchedule $manage): JsonResponse
    {
        abort_unless(OfficeAccess::schedules($request->user())->whereKey($schedule->id)->exists(), 404);
        $manage->update($schedule, $request->validated());

        return response()->json(['message' => 'Schedule updated.']);
    }

    public function destroy(Request $request, Schedule $schedule, ManageSchedule $manage): JsonResponse
    {
        Gate::authorize('schedules.manage');
        abort_unless(OfficeAccess::schedules($request->user())->whereKey($schedule->id)->exists(), 404);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $manage->delete($schedule, (int) $data['version']);

        return response()->json(['message' => 'Schedule deleted.']);
    }
}
