<x-mail::message>
# Your appointment slot is reserved

Hello {{ $appointment->client_full_name }},

Your requested schedule has no conflict, so the parish has reserved the slot while your physical documents are checked.

**Reference number:** {{ $appointment->reference_number }}  
**Service:** {{ str($appointment->service_type)->headline() }}  
**Reserved schedule:** {{ $appointment->starts_at->timezone(config('office.timezone'))->format('F j, Y \a\t g:i A') }}  
**Church:** {{ $appointment->confirmed_church }}  
**Submit documents by:** {{ $appointment->documents_due_at->timezone(config('office.timezone'))->format('F j, Y \a\t g:i A') }}
**Payment preference:** {{ $appointment->payment_option === 'online' ? 'Online via Xendit Sandbox (test only)' : 'Walk-in at the parish office' }}

Please bring every required physical document to the church office within three days. The appointment will be confirmed and shown on the public parish calendar after all required documents are verified. If the deadline passes first, the reserved slot will be released.

The service fee can be paid separately. Payment is not required to confirm your appointment and does not affect document verification or the reservation.

@if($invoice)
## Your appointment invoice

**Parish:** {{ $invoice->parish_name }}

**Invoice number:** {{ $invoice->number }}

**Issued:** {{ $invoice->issued_at->timezone(config('office.timezone'))->format('F j, Y \a\t g:i A') }} (Philippine time)

**Billed to:** {{ $invoice->client_name }}

**Appointment reference:** {{ $invoice->appointment_reference }}

**Service:** {{ $invoice->service_label }}

**Invoice total:** PHP {{ number_format($invoice->amount_cents / 100, 2) }}

@if($invoice->integrity_seal)
**Verification code:** {{ $invoice->integrity_seal }}

Check the invoice number, printed client name, and code at {{ route('documents.verify') }}. Compare the amount and status returned there with this email.
@else
**Unverified legacy copy:** No integrity seal was recorded when this invoice was issued. Ask the parish office to check its original records.
@endif

@if($invoice->amount_cents === 0)
No payment is due for this invoice.
@else
This invoice records the charge issued when you booked; it is not a payment receipt. Once your online payment is confirmed, your payment receipt and current balance will be available on your appointment's billing page.
@endif

Online checkout currently uses Xendit Sandbox for testing. No real money is collected through the sandbox.

@if($invoice->source === 'synthetic')
**DEMO — This invoice is for a simulated appointment.**
@endif

To view or print your invoice, use **View appointment** below, verify your appointment reference and contact information, then select **View / print billing statement**. That page shows your latest invoice and any recorded payments or fee changes.

@endif

<x-mail::button :url="$manageUrl">
View appointment
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
