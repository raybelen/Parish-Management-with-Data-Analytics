<?php

namespace App\Http\Controllers;

use App\Actions\ReserveAppointmentSlot;
use App\Models\Appointment;
use App\Support\OfficeSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentAvailabilityController extends Controller
{
    public function __invoke(Request $request, ReserveAppointmentSlot $slots, ?Appointment $appointment = null): JsonResponse
    {
        if ($appointment !== null) {
            abort_unless(in_array($appointment->status, ['pending', 'confirmed'], true)
                && ($appointment->status === 'pending' || $appointment->starts_at?->isFuture()), 403);
        }
        $isOtherService = ($appointment?->service_type ?? $request->input('service_type')) === 'others';
        if ($isOtherService) {
            $request->merge(['preferred_church' => Appointment::OTHER_SERVICE_LOCATION]);
        }
        $data = $request->validate([
            'group_baptism_session_id' => [$appointment === null ? 'nullable' : 'prohibited', 'required_if:baptism_mode,shared', 'prohibited_unless:baptism_mode,shared', 'integer', Rule::exists('schedules', 'id')],
            'baptism_mode' => ['nullable', Rule::in($appointment === null && in_array($request->input('service_type'), ['baptism', 'wedding', 'confirmation'], true) ? ['private', 'shared'] : ['private'])],
            'suggest' => ['nullable', 'boolean'],
            'times' => ['sometimes', 'array', 'max:12'],
            'times.*' => ['required', 'date_format:H:i', 'distinct'],
            'preferred_date' => ['required', 'date_format:Y-m-d'],
            'preferred_time' => ['required', 'date_format:H:i'],
            'service_type' => [$appointment === null ? 'required' : 'sometimes', Rule::in(array_keys(OfficeSettings::services()))],
            'preferred_church' => [$appointment === null ? 'required' : 'sometimes', Rule::in($isOtherService ? [Appointment::OTHER_SERVICE_LOCATION] : OfficeSettings::activeVenues())],
        ]);
        $candidate = $appointment === null
            ? new Appointment([...array_intersect_key($data, array_flip(['service_type', 'preferred_date', 'preferred_time', 'preferred_church', 'group_baptism_session_id'])), 'source' => OfficeSettings::source()])
            : (clone $appointment)->fill(array_intersect_key($data, array_flip(['preferred_date', 'preferred_time', ...($appointment->group_baptism_session_id ? ['group_baptism_session_id', 'preferred_church'] : [])])));
        $message = $request->input('baptism_mode') === 'shared' && ! $candidate->group_baptism_session_id
            ? 'Please choose a group session opened by the parish office.'
            : $slots->conflictMessage($candidate, memoize: true, requireOpenGroupSession: $appointment === null);
        $alternatives = [];
        if ($message !== null && $request->boolean('suggest') && ! $candidate->group_baptism_session_id) {
            foreach (['08:00', '08:30', '09:00', '09:30', '10:00', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30'] as $time) {
                $alternative = (clone $candidate)->forceFill(['preferred_time' => $time]);
                if ($slots->conflictMessage($alternative, memoize: true) === null) {
                    $alternatives[] = ['time' => $time, 'label' => CarbonImmutable::parse($time)->format('g:i A')];
                }
            }
        }

        $timeSlots = [];
        foreach ($data['times'] ?? [] as $time) {
            $slot = (clone $candidate)->forceFill(['preferred_time' => $time]);
            $conflict = $slots->conflictMessage($slot, memoize: true, requireOpenGroupSession: $appointment === null);
            $timeSlots[] = ['time' => $time, 'available' => $conflict === null, 'message' => $conflict];
        }

        return response()->json(['time_slots' => $timeSlots, 'available' => $message === null, 'message' => $message, 'alternatives' => $alternatives])
            ->header('Cache-Control', 'private, no-store');
    }
}
