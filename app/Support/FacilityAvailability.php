<?php

namespace App\Support;

use App\Models\Facility;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class FacilityAvailability
{
    public function policyConflict(Facility $facility, CarbonImmutable $start, CarbonImmutable $end, int $attendees, string $activity): ?string
    {
        if (! $facility->active) {
            return 'This facility is inactive.';
        }
        if ($attendees > $facility->capacity) {
            return 'The attendee count exceeds this facility\'s capacity.';
        }
        if (! in_array($activity, $facility->permitted_activities, true)) {
            return 'Choose an activity permitted at this facility.';
        }
        if (! $start->isSameDay($end)) {
            return 'Reservations must start and finish on the same day.';
        }
        $hours = $facility->opening_hours[$start->dayOfWeekIso] ?? [];
        if (! ($hours['open'] ?? false)) {
            return 'This facility is closed on the selected day.';
        }
        if ($start->format('H:i') < $hours['opens_at'] || $end->format('H:i') > $hours['closes_at']) {
            return 'The reservation must fit within the facility\'s opening hours.';
        }

        return null;
    }

    public function conflict(Facility $facility, CarbonImmutable $start, CarbonImmutable $end): ?string
    {
        if ($facility->bookings()->whereNull('cancelled_at')
            ->where(function (Builder $query): void {
                $query->where('status', 'confirmed')
                    ->orWhere(function (Builder $pending): void {
                        $pending->where('status', 'pending')
                            ->where(fn (Builder $active): Builder => $active->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                    });
            })
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
            return 'This time overlaps an existing reservation or closure. Choose another time.';
        }

        return null;
    }
}
