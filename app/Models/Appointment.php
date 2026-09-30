<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'status',
        'client_full_name',
        'client_contact_number',
        'client_contact_key',
        'client_email',
        'client_address',
        'preferred_contact_method',
        'service_type',
        'preferred_date',
        'preferred_time',
        'preferred_church',
        'additional_notes',
        'service_details',
        'documents',
    ];

    protected $hidden = [
        'client_contact_key',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment): void {
            if ($appointment->reference_number !== null) {
                return;
            }

            do {
                $referenceNumber = 'APT-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
            } while (static::query()->where('reference_number', $referenceNumber)->exists());

            $appointment->reference_number = $referenceNumber;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'reference_number';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'service_details' => 'array',
            'documents' => 'array',
        ];
    }
}
