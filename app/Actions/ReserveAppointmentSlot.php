<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\ParishSetting;
use App\Models\ScheduleBlock;
use App\Models\ServiceType;
use App\Support\OfficeSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReserveAppointmentSlot
{
    public function handle(Appointment $appointment): bool
    {
        return DB::transaction(function () use ($appointment): bool {
            ParishSetting::query()->where('key', 'office')->lockForUpdate()->firstOrFail();
            OfficeSettings::forgetMemoizedValues();
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $service = ServiceType::query()->where('slug', $locked->service_type)->firstOrFail();
            $start = CarbonImmutable::parse(
                $locked->preferred_date->toDateString().' '.$locked->preferred_time,
                config('office.timezone'),
            );
            $end = $start->addMinutes($service->duration_minutes);
            $reservedUntil = $end->addMinutes($service->buffer_minutes);

            if (! $start->isFuture()
                || ! OfficeSettings::venueIsActive($locked->preferred_church)
                || ! $this->fitsOperatingHours($start, $reservedUntil)
                || $this->overlapsRegularMass($start, $reservedUntil, $locked->preferred_church)
                || $this->hasConflict($locked, $start, $reservedUntil)) {
                $locked->forceFill([
                    'starts_at' => null,
                    'ends_at' => null,
                    'reserved_until' => null,
                    'confirmed_church' => null,
                    'personnel_id' => null,
                ])->save();

                return false;
            }

            $locked->forceFill([
                'starts_at' => $start,
                'ends_at' => $end,
                'reserved_until' => $reservedUntil,
                'confirmed_church' => $locked->preferred_church,
                'buffer_minutes' => $service->buffer_minutes,
                'personnel_id' => null,
            ])->save();

            return true;
        });
    }

    private function fitsOperatingHours(CarbonImmutable $start, CarbonImmutable $reservedUntil): bool
    {
        $settings = OfficeSettings::all();

        return in_array($start->dayOfWeekIso, $settings['operating_days'], true)
            && $start->format('H:i') >= $settings['opens_at']
            && $reservedUntil->isSameDay($start)
            && $reservedUntil->format('H:i') <= $settings['closes_at'];
    }

    private function hasConflict(
        Appointment $appointment,
        CarbonImmutable $start,
        CarbonImmutable $reservedUntil,
    ): bool {
        $appointmentConflict = Appointment::query()
            ->where('source', $appointment->source)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereKeyNot($appointment->id)
            ->where('confirmed_church', $appointment->preferred_church)
            ->where('starts_at', '<', $reservedUntil)
            ->where('reserved_until', '>', $start)
            ->exists();
        $blocked = ScheduleBlock::query()
            ->where('church', $appointment->preferred_church)
            ->where('starts_at', '<', $reservedUntil)
            ->where('ends_at', '>', $start)
            ->exists();

        return $appointmentConflict
            || $blocked
            || ManageSchedule::conflicts($start, $reservedUntil, $appointment->preferred_church, null);
    }

    private function overlapsRegularMass(CarbonImmutable $start, CarbonImmutable $end, string $church): bool
    {
        foreach (config('parish.regular_schedules', []) as $schedule) {
            if ($church !== ($schedule['church'] ?? config('parish.churches.0'))) {
                continue;
            }
            if (! in_array($start->dayOfWeekIso, $schedule['days'], true)) {
                continue;
            }

            foreach ($schedule['times'] as $time) {
                $massStart = $start->setTimeFromTimeString($time['value']);
                $massEnd = $massStart
                    ->addMinutes($schedule['duration_minutes'] ?? 60)
                    ->addMinutes($schedule['buffer_minutes'] ?? 30);

                if ($start < $massEnd && $end > $massStart) {
                    return true;
                }
            }
        }

        return false;
    }
}
