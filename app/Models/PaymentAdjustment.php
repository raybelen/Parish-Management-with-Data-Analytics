<?php

namespace App\Models;

use Database\Factories\PaymentAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAdjustment extends Model
{
    /** @use HasFactory<PaymentAdjustmentFactory> */
    use HasFactory;

    protected $fillable = ['appointment_id', 'requested_by', 'idempotency_key', 'kind', 'payload', 'reason'];

    protected $attributes = ['status' => 'pending'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payload' => 'array', 'reviewed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (PaymentAdjustment $adjustment): void {
            if ($adjustment->getOriginal('status') !== 'pending'
                || $adjustment->isDirty(['appointment_id', 'requested_by', 'idempotency_key', 'kind', 'payload', 'reason'])) {
                throw new \LogicException('Adjustment requests and completed reviews cannot be changed.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Adjustment requests must be retained.'));
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
