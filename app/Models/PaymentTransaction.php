<?php

namespace App\Models;

use Database\Factories\PaymentTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    /** @use HasFactory<PaymentTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'public_id', 'appointment_id', 'ledger_payment_id', 'provider', 'environment', 'reference_id',
        'provider_session_id', 'provider_payment_id', 'provider_business_id', 'amount_cents', 'currency',
        'status', 'payment_method', 'checkout_url', 'failure_code', 'failure_message', 'expires_at',
        'paid_at', 'last_event_at',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Payment transactions cannot be deleted.'));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'expires_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'last_event_at' => 'immutable_datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function ledgerPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'ledger_payment_id');
    }
}
