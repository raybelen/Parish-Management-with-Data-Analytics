<?php

namespace App\Models;

use Database\Factories\OfficeNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeNotification extends Model
{
    /** @use HasFactory<OfficeNotificationFactory> */
    use HasFactory;

    protected $fillable = ['appointment_id', 'facility_booking_id', 'event', 'message', 'deduplication_key', 'read_at', 'read_by'];

    public function facilityBooking(): BelongsTo
    {
        return $this->belongsTo(FacilityBooking::class);
    }

    #[Scope]
    protected function forSource(Builder $query, string $source): void
    {
        $query->where(function (Builder $query) use ($source): void {
            $query->whereHas('appointment', fn (Builder $query): Builder => $query->where('source', $source))
                ->orWhereHas('facilityBooking.facility', fn (Builder $query): Builder => $query->where('source', $source));
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
