<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\Schedule;
use App\Models\ServiceType;
use App\Support\AppointmentParticipants;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAppointment
{
    public function __construct(private ReserveAppointmentSlot $reserveAppointmentSlot) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(array $validated): Appointment
    {
        if (! empty($validated['group_baptism_session_id']) || ($validated['baptism_mode'] ?? 'private') === 'shared') {
            throw ValidationException::withMessages(['group_baptism_session_id' => 'Shared baptisms are currently unavailable. Please book one baptism appointment.']);
        }

        return DB::transaction(function () use ($validated): Appointment {
            $appointment = Appointment::create([
                ...Arr::only($validated, [
                    'client_full_name',
                    'client_contact_number',
                    'client_email',
                    'client_address',
                    'preferred_contact_method',
                    'service_type',
                    'preferred_date',
                    'preferred_time',
                    'preferred_church',
                    'additional_notes',
                    'group_baptism_session_id',
                ]),
                'client_contact_key' => preg_replace('/\D+/', '', $validated['client_contact_number']) ?? '',
                'service_details' => $validated['service'],
                'documents' => [],
                'source' => OfficeSettings::source(),
                'requirements' => ServiceType::query()->where('slug', $validated['service_type'])->value('requirements') ?? config('appointments.services.'.$validated['service_type'].'.documents', []),
                'fee_cents' => ServiceType::query()->where('slug', $validated['service_type'])->value('fee_cents') ?? 0,
            ]);

            AppointmentParticipants::sync($appointment);
            $this->reserveAppointmentSlot->handle($appointment);
            if ($appointment->group_baptism_session_id) {
                $session = Schedule::query()->findOrFail($appointment->group_baptism_session_id);
                if ($session->group_fee !== null) {
                    $appointment->update(['fee_cents' => (int) round((float) $session->group_fee * 100)]);
                }
            }
            OfficeAudit::record('appointment.created', $appointment, [], ['reference_number' => $appointment->reference_number, 'service_type' => $appointment->service_type]);
            OfficeAudit::notify($appointment, 'new', 'New appointment request: '.$appointment->reference_number);

            return $appointment->refresh();
        });
    }
}
