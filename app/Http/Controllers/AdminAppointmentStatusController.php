<?php

namespace App\Http\Controllers;

use App\Actions\ManageAppointment;
use App\Models\Appointment;
use App\Support\OfficeAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminAppointmentStatusController extends Controller
{
    public function store(Request $request, Appointment $appointment, ManageAppointment $manage): RedirectResponse
    {
        OfficeAccess::appointment($request->user(), $appointment);
        abort_unless($request->user()->can('appointments.manage') || $request->user()->can('appointments.approve'), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(Appointment::STATUSES)], 'version' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:1000'],
            'completed_at' => ['required_if:status,completed', 'nullable', 'date_format:Y-m-d\TH:i', 'before_or_equal:now'],
        ]);
        abort_unless($request->user()->can('appointments.manage') || in_array($data['status'], ['confirmed', 'declined'], true), 403);
        $manage->status($appointment, $data['status'], (int) $data['version'], $data['reason'], $data['completed_at'] ?? null);

        return back()->with('status', 'Appointment status updated.');
    }
}
