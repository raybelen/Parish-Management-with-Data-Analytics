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
            abort_unless($appointment->status === 'pending', 403);
        }
        $data = $request->validate([
            'group_baptism_session_id' => ['prohibited'],
            'baptism_mode' => ['nullable', Rule::in(['private'])],
            'suggest' => ['nullable', 'boolean'],
            'preferred_date' => ['required', 'date_format:Y-m-d'],
            'preferred_time' => ['required', 'date_format:H:i'],
            'service_type' => [$appointment === null ? 'required' : 'sometimes', Rule::in(array_keys(OfficeSettings::services()))],
            'preferred_church' => [$appointment === null ? 'required' : 'sometimes', Rule::in(OfficeSettings::activeVenues())],
        ]);
        $candidate = $appointment === null
            ? new Appointment([...array_intersect_key($data, array_flip(['service_type', 'preferred_date', 'preferred_time', 'preferred_church', 'group_baptism_session_id'])), 'source' => OfficeSettings::source()])
            : (clone $appointment)->fill(array_intersect_key($data, array_flip(['preferred_date', 'preferred_time', ...($appointment->group_baptism_session_id ? ['group_baptism_session_id', 'preferred_church'] : [])])));
        $message = $request->input('baptism_mode') === 'shared' && ! $candidate->group_baptism_session_id
            ? 'Please choose an authorized Sunday group baptism session.'
            : $slots->conflictMessage($candidate);
        $alternatives = [];
        if ($message !== null && $request->boolean('suggest') && ! $candidate->group_baptism_session_id) {
            foreach (['08:00', '08:30', '09:00', '09:30', '10:00', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30'] as $time) {
                $alternative = (clone $candidate)->forceFill(['preferred_time' => $time]);
                if ($slots->conflictMessage($alternative) === null) {
                    $alternatives[] = ['time' => $time, 'label' => CarbonImmutable::parse($time)->format('g:i A')];
                }
            }
        }

        return response()->json(['available' => $message === null, 'message' => $message, 'alternatives' => $alternatives])
            ->header('Cache-Control', 'private, no-store');
    }
}
