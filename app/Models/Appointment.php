<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Appointment extends Model
{
    public const STATUSES = ['pending', 'confirmed', 'completed', 'cancelled', 'declined', 'no_show'];

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
        'source', 'personnel_id', 'confirmed_church', 'starts_at', 'ends_at', 'reserved_until',
        'group_baptism_session_id', 'buffer_minutes', 'fee_cents', 'completed_at', 'outcome', 'office_notes',
        'requirements', 'physical_documents', 'online_verification', 'version',
    ];

    protected $hidden = [
        'client_contact_key',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Appointments must be preserved as historical records.'));
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
            'requirements' => 'array',
            'physical_documents' => 'array',
            'online_verification' => 'array',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'reserved_until' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'fee_cents' => 'integer',
            'group_baptism_session_id' => 'integer',
            'buffer_minutes' => 'integer',
            'version' => 'integer',
        ];
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(AppointmentParticipant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function serviceRecord(): HasOne
    {
        return $this->hasOne(ServiceRecord::class);
    }

    /** @return array<string, array<string, mixed>> */
    public function documentRequirements(): array
    {
        return $this->requirements ?? config('appointments.services.'.$this->service_type.'.documents', []);
    }

    public function physicalDocumentsComplete(): bool
    {
        foreach ($this->documentRequirements() as $key => $requirement) {
            if (($requirement['required'] ?? false) && ! ($this->physical_documents[$key]['verified'] ?? false)) {
                return false;
            }
        }

        return true;
    }

    public function netPaidCents(): int
    {
        return (int) $this->payments->sum(fn (Payment $payment): int => $payment->kind === 'refund' ? -$payment->amount_cents : $payment->amount_cents);
    }

    public function balanceCents(): int
    {
        return max(0, $this->fee_cents - $this->netPaidCents());
    }
}
