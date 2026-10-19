<?php

namespace App\Actions;

use App\Mail\AppointmentConfirmed;
use App\Models\Appointment;
use App\Models\ParishSetting;
use App\Models\Personnel;
use App\Models\ScheduleBlock;
use App\Models\ServiceRecord;
use App\Models\ServiceType;
use App\Support\AppointmentParticipants;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ManageAppointment
{
    /** @param array<string, mixed> $data */
    public function schedule(Appointment $appointment, array $data): void
    {
        DB::transaction(function () use ($appointment, $data): void {
            ParishSetting::query()->where('key', 'office')->lockForUpdate()->firstOrFail();
            OfficeSettings::forgetMemoizedValues();
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $this->checkVersion($locked, (int) $data['version']);
            $this->require(in_array($locked->status, ['pending', 'confirmed'], true), 'status', 'Only pending or confirmed appointments can be scheduled.');
            $service = ServiceType::query()->where('slug', $locked->service_type)->firstOrFail();
            $start = CarbonImmutable::parse($data['starts_at'], config('office.timezone'));
            $end = $start->addMinutes((int) $data['duration_minutes']);
            $buffer = (int) $data['buffer_minutes'];
            $reservedUntil = $end->addMinutes($buffer);
            $this->require($buffer >= $service->buffer_minutes, 'buffer_minutes', 'The preparation gap cannot be shorter than the service minimum.');
            $this->require(Personnel::query()->whereKey($data['personnel_id'])->where('active', true)->exists(), 'personnel_id', 'Choose active personnel.');
            $this->require(OfficeSettings::venueIsActive($data['confirmed_church']), 'confirmed_church', 'Choose a church or chapel that is currently available.');
            $this->require($start->isFuture(), 'starts_at', 'Choose a future schedule.');
            $this->checkOperatingHours($start, $reservedUntil);
            $conflicts = Appointment::query()->where('source', $locked->source)->whereIn('status', ['pending', 'confirmed'])
                ->whereKeyNot($locked->id)
                ->where(function ($query) use ($data): void {
                    $query->where('confirmed_church', $data['confirmed_church'])->orWhere('personnel_id', $data['personnel_id']);
                })
                ->where('starts_at', '<', $reservedUntil)->where('reserved_until', '>', $start)->exists();
            $blocks = ScheduleBlock::query()->where('starts_at', '<', $reservedUntil)->where('ends_at', '>', $start)
                ->where(function ($query) use ($data): void {
                    $query->where('church', $data['confirmed_church'])->orWhere('personnel_id', $data['personnel_id']);
                })->exists();
            $events = ManageSchedule::conflicts($start, $reservedUntil, $data['confirmed_church'], (int) $data['personnel_id']);
            $this->require(! $conflicts && ! $blocks && ! $events, 'starts_at', 'The venue or personnel is unavailable during this ceremony or preparation gap.');
            $this->checkRegularMass($start, $reservedUntil, $data['confirmed_church']);
            $before = $locked->only(['starts_at', 'ends_at', 'reserved_until', 'confirmed_church', 'personnel_id', 'fee_cents', 'office_notes']);
            $locked->fill([
                'starts_at' => $start, 'ends_at' => $end, 'reserved_until' => $reservedUntil,
                'confirmed_church' => $data['confirmed_church'], 'personnel_id' => $data['personnel_id'],
                'buffer_minutes' => $buffer, 'office_notes' => $data['office_notes'] ?? null,
                'version' => $locked->version + 1,
            ])->save();
            OfficeAudit::record('appointment.scheduled', $locked, $before, $locked->only(array_keys($before)), $data['reason']);
            OfficeAudit::notify($locked, 'schedule', 'Schedule updated for '.$locked->reference_number);
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
                $this->require($locked->physicalDocumentsComplete(), 'status', 'Verify all required physical documents before confirmation.');
                $this->require($locked->balanceCents() === 0, 'status', 'Record the full payment before confirmation.');
                $this->require($locked->starts_at !== null && $locked->starts_at->isFuture(), 'status', 'Save a future confirmed schedule first.');
                $this->require($locked->ends_at !== null && $locked->reserved_until !== null && $locked->confirmed_church !== null, 'status', 'Save a complete schedule before confirmation.');
                $this->require(Personnel::query()->whereKey($locked->personnel_id)->where('active', true)->exists(), 'status', 'Assigned personnel must be active.');
                $this->require(OfficeSettings::venueIsActive($locked->confirmed_church), 'status', 'The assigned venue is currently unavailable. Choose another venue before confirmation.');
                $minimumGap = ServiceType::query()->where('slug', $locked->service_type)->value('buffer_minutes');
                $this->require($locked->buffer_minutes >= $minimumGap, 'status', 'The preparation gap has changed. Save the schedule with the current minimum before confirmation.');
                $this->checkOperatingHours($locked->starts_at, $locked->reserved_until);
                $conflict = Appointment::query()->where('source', $locked->source)->whereIn('status', ['pending', 'confirmed'])->whereKeyNot($locked->id)
                    ->where('starts_at', '<', $locked->reserved_until)->where('reserved_until', '>', $locked->starts_at)
                    ->where(fn ($query) => $query->where('confirmed_church', $locked->confirmed_church)->orWhere('personnel_id', $locked->personnel_id))->exists();
                $blocked = ScheduleBlock::query()->where('starts_at', '<', $locked->reserved_until)->where('ends_at', '>', $locked->starts_at)
                    ->where(fn ($query) => $query->where('church', $locked->confirmed_church)->orWhere('personnel_id', $locked->personnel_id))->exists();
                $events = ManageSchedule::conflicts($locked->starts_at, $locked->reserved_until, $locked->confirmed_church, $locked->personnel_id);
                $this->require(! $conflict && ! $blocked && ! $events, 'status', 'The schedule is no longer available. Choose another time.');
                $this->checkRegularMass($locked->starts_at, $locked->reserved_until, $locked->confirmed_church);
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

            if ($status === 'confirmed' && $locked->client_email !== null) {
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

    private function checkOperatingHours(CarbonImmutable $start, CarbonImmutable $reservedUntil): void
    {
        $settings = OfficeSettings::all();
        $this->require(in_array($start->dayOfWeekIso, $settings['operating_days'], true)
            && $start->format('H:i') >= $settings['opens_at']
            && $reservedUntil->isSameDay($start)
            && $reservedUntil->format('H:i') <= $settings['closes_at'], 'starts_at', 'The ceremony and preparation gap must fit within operating hours.');
    }

    private function checkRegularMass(CarbonImmutable $start, CarbonImmutable $end, string $church): void
    {
        if ($church !== config('parish.churches.0')) {
            return;
        }
        foreach (config('parish.regular_schedules', []) as $schedule) {
            if (! in_array($start->dayOfWeekIso, $schedule['days'], true)) {
                continue;
            }
            foreach ($schedule['times'] as $time) {
                $massStart = $start->setTimeFromTimeString($time['value']);
                $massEnd = $massStart->addMinutes($schedule['duration_minutes'] ?? 60)->addMinutes($schedule['buffer_minutes'] ?? 30);
                $this->require(! ($start < $massEnd && $end > $massStart), 'starts_at', 'This schedule overlaps a regular Mass or its preparation gap.');
            }
        }
    }

    private function require(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
