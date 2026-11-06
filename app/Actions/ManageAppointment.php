<?php

namespace App\Actions;

use App\Mail\AppointmentConfirmed;
use App\Models\Appointment;
use App\Models\ParishSetting;
use App\Models\Personnel;
use App\Models\ServiceRecord;
use App\Support\AppointmentParticipants;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ManageAppointment
{
    public function __construct(private ReserveAppointmentSlot $slots) {}

    /** @param array<string, mixed> $data */
    public function assignPersonnel(Appointment $appointment, array $data): void
    {
        DB::transaction(function () use ($appointment, $data): void {
            ParishSetting::query()->where('key', 'office')->lockForUpdate()->firstOrFail();
            OfficeSettings::forgetMemoizedValues();
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $this->checkVersion($locked, (int) $data['version']);
            $this->require(in_array($locked->status, ['pending', 'confirmed'], true), 'status', 'Only active appointments can be assigned.');
            $this->require($locked->starts_at !== null && $locked->confirmed_church !== null, 'personnel_id', 'This appointment needs a confirmed schedule before personnel can be assigned.');
            $this->require(Personnel::query()->whereKey($data['personnel_id'])->where('active', true)->exists(), 'personnel_id', 'Choose active personnel.');
            $this->validateSchedule($locked, $locked->starts_at, $locked->confirmed_church, (int) $data['personnel_id']);
            $before = ['personnel_id' => $locked->personnel_id];
            $locked->forceFill([
                'personnel_id' => (int) $data['personnel_id'],
                'version' => $locked->version + 1,
            ])->save();
            OfficeAudit::record('appointment.personnel_assigned', $locked, $before, ['personnel_id' => $locked->personnel_id]);
            OfficeAudit::notify($locked, 'assignment', 'Personnel assigned for '.$locked->reference_number);
        });
    }

    public function status(Appointment $appointment, string $status, int $version, string $reason, ?string $completedAt = null): void
    {
        DB::transaction(function () use ($appointment, $status, $version, $reason, $completedAt): void {
            ParishSetting::query()->where('key', 'office')->lockForUpdate()->firstOrFail();
            OfficeSettings::forgetMemoizedValues();
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $this->checkVersion($locked, $version);
            $allowed = match ($locked->status) {
                'pending' => ['confirmed', 'cancelled', 'declined'],
                'confirmed' => ['completed', 'cancelled', 'no_show'],
                default => [],
            };
            $this->require(in_array($status, $allowed, true), 'status', 'This status transition is not permitted.');
            if ($status === 'confirmed') {
                $this->require($locked->physicalDocumentsComplete(), 'status', 'Verify every required physical document before confirmation.');
                $this->require($locked->documents_due_at === null || $locked->documents_due_at->isFuture(), 'status', 'The document submission deadline has passed.');
                $this->require($locked->starts_at !== null && $locked->starts_at->isFuture(), 'status', 'Save a future confirmed schedule first.');
                $this->require($locked->ends_at !== null && $locked->reserved_until !== null && $locked->confirmed_church !== null, 'status', 'Save a complete schedule before confirmation.');
                $this->require(OfficeSettings::venueIsActive($locked->confirmed_church), 'status', 'The assigned venue is currently unavailable. Choose another venue before confirmation.');
                $this->require($locked->ends_at->equalTo($locked->starts_at->addHour()), 'starts_at', 'Save a one-hour service schedule before confirmation.');
                $this->validateSchedule($locked, $locked->starts_at, $locked->confirmed_church, $locked->personnel_id);
            }
            $before = $locked->only(['status', 'completed_at', 'outcome']);
            if ($status === 'completed') {
                $completion = CarbonImmutable::parse($completedAt, config('office.timezone'));
                $this->require($completedAt !== null && ! $completion->isFuture() && $completion >= $locked->starts_at, 'completed_at', 'Completion must be between the scheduled start and now.');
                $locked->completed_at = $completion;
                AppointmentParticipants::sync($locked);
                $participants = $locked->participants()->get(['role', 'full_name', 'date_of_birth'])->toArray();
                ServiceRecord::firstOrCreate(['appointment_id' => $locked->id], [
                    'record_number' => 'REC-'.$locked->reference_number, 'source' => $locked->source,
                    'service_type' => $locked->service_type, 'service_date' => $completion->toDateString(),
                    'participant_names' => collect($participants)->whereIn('role', ['child', 'bride', 'groom', 'deceased', 'recipient'])->pluck('full_name')->implode(' & '),
                    'participants' => $participants, 'details' => $locked->service_details,
                    'church' => $locked->confirmed_church, 'officiant' => $locked->personnel?->name,
                    'recorded_by' => auth()->id(),
                ]);
            }
            if ($status === 'no_show') {
                $this->require($locked->ends_at !== null && $locked->ends_at->isPast(), 'status', 'A no-show can only be recorded after the scheduled end.');
            }
            $locked->status = $status;
            $locked->outcome = $reason;
            $locked->version++;
            $locked->save();
            OfficeAudit::record('appointment.'.$status, $locked, $before, $locked->only(array_keys($before)), $reason);
            OfficeAudit::notify($locked, $status, ucfirst(str_replace('_', ' ', $status)).': '.$locked->reference_number);

            if ($status === 'confirmed' && $locked->wantsEmailNotifications()) {
                Mail::to($locked->client_email)->queue(
                    (new AppointmentConfirmed($locked))->afterCommit(),
                );
            }
        });
    }

    private function checkVersion(Appointment $appointment, int $version): void
    {
        $this->require($appointment->version === $version, 'version', 'This appointment changed since you opened it. Refresh before saving.');
    }

    private function validateSchedule(Appointment $appointment, CarbonImmutable $start, string $venue, ?int $personnelId): void
    {
        $candidate = (clone $appointment)->forceFill([
            'preferred_date' => $start->toDateString(), 'preferred_time' => $start->format('H:i'), 'preferred_church' => $venue,
        ]);
        $message = $this->slots->conflictMessage($candidate, $personnelId);
        $this->require($message === null, 'starts_at', $message ?? 'This schedule is unavailable.');
    }

    private function require(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
