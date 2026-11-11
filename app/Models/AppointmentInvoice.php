<?php

namespace App\Models;

use App\Support\FinancialDocumentSeal;
use Database\Factories\AppointmentInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentInvoice extends Model
{
    /** @use HasFactory<AppointmentInvoiceFactory> */
    use HasFactory;

    protected $fillable = ['appointment_id', 'issued_by', 'number', 'revision', 'parish_name', 'client_name', 'appointment_reference', 'service_label', 'source', 'amount_cents', 'issued_at'];

    protected $hidden = ['integrity_seal'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['issued_at' => 'immutable_datetime', 'amount_cents' => 'integer', 'revision' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (AppointmentInvoice $invoice): void {
            $invoice->integrity_seal = FinancialDocumentSeal::sign($invoice);
        });
        static::updating(fn () => throw new \LogicException('Issued invoices are immutable. Issue a replacement instead.'));
        static::deleting(fn () => throw new \LogicException('Issued invoices must be retained.'));
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
