<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\ParishSetting;
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
    public function __construct(private ReserveAppointmentSlot $reserveAppointmentSlot, private IssueAppointmentInvoice $issueInvoice) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(array $validated): Appointment
    {
        if (($validated['baptism_mode'] ?? 'private') === 'shared' && empty($validated['group_baptism_session_id'])) {
            throw ValidationException::withMessages(['group_baptism_session_id' => 'Please choose a group session opened by the parish office.']);
        }

        return DB::transaction(function () use ($validated): Appointment {
            ParishSetting::query()->where('key', 'office')->lockForUpdate()->firstOrFail();
            OfficeSettings::forgetMemoizedValues();
            $session = empty($validated['group_baptism_session_id']) ? null : Schedule::query()->find($validated['group_baptism_session_id']);
            if (! empty($validated['group_baptism_session_id']) && (! $session || ! $session->group_booking_open || $session->category !== 'Group '.ucfirst($validated['service_type']) || $session->status !== 'scheduled' || $session->source !== OfficeSettings::source())) {
                throw ValidationException::withMessages(['group_baptism_session_id' => 'This group session is not open for bookings. Please choose another session.']);
            }
            $serviceType = ServiceType::query()->where('slug', $validated['service_type'])->first(['requirements', 'fee_cents']);
            $feeCents = $session?->group_fee !== null ? (int) round((float) $session->group_fee * 100) : ($serviceType?->fee_cents ?? 0);
            if ($validated['service_type'] === 'others') {
                $feeCents = RecordPayment::cents((string) $validated['offering_amount']);
            }
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
                'requirements' => $serviceType?->requirements ?? config('appointments.services.'.$validated['service_type'].'.documents', []),
                'fee_cents' => $feeCents,
                'payment_option' => $validated['payment_option'] ?? 'walk_in',
                'payment_status' => $feeCents > 0 ? 'unpaid' : 'not_required',
            ]);

            AppointmentParticipants::sync($appointment);
            $this->reserveAppointmentSlot->handle($appointment);
            if ($appointment->payment_option === 'online' && $feeCents > 0) {
                $this->issueInvoice->handle($appointment, $feeCents);
            }
            OfficeAudit::record('appointment.created', $appointment, [], ['reference_number' => $appointment->reference_number, 'service_type' => $appointment->service_type]);
            OfficeAudit::notify($appointment, 'new', ($appointment->requiresOfficeApproval() ? 'New service request awaiting office approval: ' : 'New appointment awaiting documents: ').$appointment->reference_number);

            return $appointment->refresh();
        });
    }
}
