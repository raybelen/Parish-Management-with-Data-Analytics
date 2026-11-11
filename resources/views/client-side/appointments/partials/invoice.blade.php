<section id="invoice-{{ $invoice->id }}" data-billing-document class="mx-auto mt-8 max-w-4xl scroll-mt-6 rounded-xl border border-navy/15 bg-white p-5 sm:p-10 print:m-0 print:max-w-none print:break-before-page print:border-0 print:p-0">
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-navy/15 pb-6">
        <div><p class="text-sm text-muted">{{ $invoice->parish_name }}</p><h2 class="mt-2 text-3xl">Invoice</h2><p class="mt-2 font-semibold">{{ $invoice->number }}</p></div>
        <button type="button" data-print-billing class="admin-button print:hidden">Print / Save invoice PDF</button>
    </div>
    @if($invoice->source === 'synthetic')<p class="mt-5 font-bold">DEMO — Simulated appointment.</p>@endif
    @if($invoice->id !== $appointment->invoices->first()->id)
        <p class="mt-5 font-bold">SUPERSEDED — Replaced by {{ $appointment->invoices->first()->number }}. Do not pay this invoice separately.</p>
    @elseif($invoice->amount_cents !== $appointment->fee_cents)
        <p class="mt-5 font-bold">FEE CHANGED — The office must issue a replacement invoice. Refer to the current billing statement before paying.</p>
    @else
        <p class="mt-5 font-semibold">Current payment status: {{ $appointment->billingStatusLabel() }}</p>
    @endif
    <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2 print:grid-cols-2">
        <div><dt class="text-muted">Issued</dt><dd>{{ $invoice->issued_at->timezone(config('office.timezone'))->format('M j, Y g:i A') }} (Philippine time)</dd></div>
        <div><dt class="text-muted">Appointment reference</dt><dd>{{ $invoice->appointment_reference }}</dd></div>
        <div><dt class="text-muted">Billed to</dt><dd class="break-words">{{ $invoice->client_name }}</dd></div>
        <div><dt class="text-muted">Revision</dt><dd>{{ $invoice->revision }}</dd></div>
    </dl>
    <table class="mt-6 w-full text-left text-sm"><caption class="mb-3 text-left font-semibold">Charges as issued (PHP)</caption><thead><tr class="border-b border-navy/15"><th class="py-3">Service</th><th class="py-3 text-right">Amount</th></tr></thead><tbody><tr><td class="py-4">{{ $invoice->service_label }}</td><td class="py-4 text-right">₱{{ number_format($invoice->amount_cents / 100, 2) }}</td></tr></tbody></table>
    <p class="border-t border-navy/15 pt-5 text-right text-xl font-semibold">Invoice total: ₱{{ number_format($invoice->amount_cents / 100, 2) }}</p>
    @if($invoice->id === $appointment->invoices->first()->id && $invoice->amount_cents === $appointment->fee_cents)
        <p class="mt-4 text-sm">Paid after refunds: ₱{{ number_format($appointment->netPaidCents() / 100, 2) }} · Balance: ₱{{ number_format($appointment->balanceCents() / 100, 2) }} as of {{ now(config('office.timezone'))->format('M j, Y g:i A') }}.</p>
        @if($appointment->payments->contains(fn ($entry) => $entry->isSandbox()))<p class="mt-3 font-semibold">Demonstration balance includes sandbox entries. No real money moves through the sandbox.</p>@endif
    @endif
    <p class="mt-6 text-xs leading-5 text-muted">This invoice records the service charge, not proof of payment. Payments and refunds are linked through the appointment reference. Replacement invoices replace the full charge; their totals must not be added together. Payment does not by itself confirm an appointment.</p>
    @include('client-side.appointments.partials.document-verification', ['document' => $invoice])
</section>
