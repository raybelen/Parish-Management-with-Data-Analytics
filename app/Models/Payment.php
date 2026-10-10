<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = ['appointment_id', 'recorded_by', 'original_payment_id', 'idempotency_key', 'kind', 'amount_cents', 'method', 'reference', 'notes', 'paid_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['paid_at' => 'immutable_datetime', 'amount_cents' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Payment entries are immutable. Record a refund instead.'));
        static::deleting(fn () => throw new \LogicException('Payment entries cannot be deleted.'));
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
