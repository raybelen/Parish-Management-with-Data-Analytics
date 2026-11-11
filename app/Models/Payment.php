<?php

namespace App\Models;

use App\Support\FinancialDocumentSeal;
use App\Support\OfficeSettings;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = ['appointment_id', 'recorded_by', 'original_payment_id', 'idempotency_key', 'kind', 'amount_cents', 'method', 'reference', 'notes', 'paid_at'];

    protected $hidden = ['integrity_seal', 'receipt_snapshot'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['paid_at' => 'immutable_datetime', 'amount_cents' => 'integer', 'receipt_snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $appointment = $payment->appointment()->firstOrFail();
            $payment->receipt_snapshot = [
                'number' => 'PAY-'.Str::ulid(),
                'parish_name' => OfficeSettings::all()['parish_name'],
                'client_name' => $appointment->client_full_name,
                'appointment_reference' => $appointment->reference_number,
                'service_label' => OfficeSettings::services()[$appointment->service_type]['label'] ?? Str::headline($appointment->service_type),
                'source' => $appointment->source,
            ];
            $payment->integrity_seal = FinancialDocumentSeal::sign($payment);
        });
        static::updating(fn () => throw new \LogicException('Payment entries are immutable. Record a refund instead.'));
        static::deleting(fn () => throw new \LogicException('Payment entries cannot be deleted.'));
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function receiptNumber(): string
    {
        return $this->receipt_snapshot['number'] ?? 'PAY-'.$this->id;
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
