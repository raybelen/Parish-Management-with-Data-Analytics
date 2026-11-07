<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function originalPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_payment_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(PaymentTransaction::class, 'ledger_payment_id');
    }

    public function isSandbox(): bool
    {
        return $this->method === 'xendit_sandbox' || ($this->original_payment_id !== null && $this->originalPayment?->method === 'xendit_sandbox');
    }

    #[Scope]
    protected function sandboxEntries(Builder $query): void
    {
        $query->where(fn (Builder $entries): Builder => $entries->where('payments.method', 'xendit_sandbox')
            ->orWhereHas('originalPayment', fn (Builder $original): Builder => $original->where('method', 'xendit_sandbox')));
    }
}
