<?php

namespace App\Models;

use Database\Factories\FacilityBookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityBooking extends Model
{
    /** @use HasFactory<FacilityBookingFactory> */
    use HasFactory;

    protected $fillable = ['status', 'reference', 'contact_email', 'facility_id', 'recorded_by', 'kind', 'title', 'organizer', 'activity', 'attendees', 'starts_at', 'ends_at', 'expires_at', 'cancelled_at', 'cancellation_reason'];

    protected function casts(): array
    {
        return ['attendees' => 'integer', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
