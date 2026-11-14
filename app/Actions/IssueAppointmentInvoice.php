<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\AppointmentInvoice;
use App\Support\FinancialDocumentSeal;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueAppointmentInvoice
{
    public function handle(Appointment $appointment, int $expectedFeeCents): AppointmentInvoice
    {
        return DB::transaction(function () use ($appointment, $expectedFeeCents): AppointmentInvoice {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless($locked->fee_cents === $expectedFeeCents, 409, 'The fee has changed. Refresh the appointment before issuing the invoice.');
            $previous = $locked->invoices()->latest('revision')->first();
            abort_if($previous?->integrity_seal !== null && ! FinancialDocumentSeal::valid($previous), 409, 'This invoice could not be verified. Contact the parish administrator.');
            if ($previous && $previous->integrity_seal !== null && $previous->amount_cents === $locked->fee_cents) {
                return $previous;
            }

            $revision = ($previous?->revision ?? 0) + 1;
            $invoice = $locked->invoices()->create([
                'issued_by' => auth()->id(),
                'number' => 'INV-'.str_pad((string) $locked->id, 8, '0', STR_PAD_LEFT).'-'.$revision,
                'revision' => $revision,
                'parish_name' => OfficeSettings::all()['parish_name'],
                'client_name' => $locked->client_full_name,
                'appointment_reference' => $locked->reference_number,
                'service_label' => OfficeSettings::services()[$locked->service_type]['label'] ?? Str::headline($locked->service_type),
                'source' => $locked->source,
                'amount_cents' => $locked->fee_cents,
                'issued_at' => now(),
            ]);
            OfficeAudit::record('invoice.issued', $invoice, [], ['number' => $invoice->number, 'appointment_id' => $locked->id, 'amount_cents' => $invoice->amount_cents, 'replaces' => $previous?->number]);

            return $invoice;
        });
    }
}
