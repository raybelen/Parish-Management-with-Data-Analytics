<section id="receipt-{{ $payment->id }}" data-billing-document class="mx-auto mt-8 max-w-4xl scroll-mt-6 rounded-xl border border-navy/15 bg-white p-5 sm:p-10 print:m-0 print:max-w-none print:break-before-page print:border-0 print:p-0">
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-navy/15 pb-6">
        <div><p class="text-sm text-muted">{{ $payment->receipt_snapshot['parish_name'] ?? $parishName }}</p><h2 class="mt-2 text-3xl">Payment receipt</h2><p class="mt-2 break-all font-semibold">{{ $payment->receiptNumber() }}</p></div>
        <button type="button" data-print-billing class="admin-button print:hidden">Print / Save receipt PDF</button>
    </div>
    @if($payment->isSandbox() || ($payment->receipt_snapshot['source'] ?? $appointment->source) === 'synthetic')<p class="mt-5 font-bold">Test transaction</p>@endif
    <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2 print:grid-cols-2">
        <div><dt class="text-muted">Date received</dt><dd>{{ $payment->paid_at->timezone(config('office.timezone'))->format('M j, Y g:i A') }} (Philippine time)</dd></div>
        <div><dt class="text-muted">Appointment reference</dt><dd>{{ $payment->receipt_snapshot['appointment_reference'] ?? $appointment->reference_number }}</dd></div>
        <div><dt class="text-muted">Client</dt><dd class="break-words">{{ $payment->receipt_snapshot['client_name'] ?? $appointment->client_full_name }}</dd></div>
        <div><dt class="text-muted">Service</dt><dd>{{ $payment->receipt_snapshot['service_label'] ?? $serviceLabel }}</dd></div>
        <div><dt class="text-muted">Payment method</dt><dd>{{ $payment->method === 'xendit_sandbox' ? 'Online Payment' : str($payment->method)->headline() }}</dd></div>
        <div><dt class="text-muted">Transaction reference</dt><dd class="break-all">{{ $payment->reference ?: 'Not recorded' }}</dd></div>
    </dl>
    <p class="mt-6 border-y border-navy/15 py-5 text-2xl font-semibold">Amount received: ₱{{ number_format($payment->amount_cents / 100, 2) }}</p>
    @foreach($appointment->payments->where('kind', 'refund')->where('original_payment_id', $payment->id) as $refund)
        <p class="mt-4 text-sm">Refund #{{ $refund->id }}: ₱{{ number_format($refund->amount_cents / 100, 2) }} on {{ $refund->paid_at->timezone(config('office.timezone'))->format('M j, Y g:i A') }}.</p>
    @endforeach
    <p class="mt-6 text-xs leading-5 text-muted">This receipt confirms the payment shown above. Any recorded refunds are shown separately; this receipt does not establish the current amount due. Prepared {{ now(config('office.timezone'))->format('M j, Y g:i A') }} (Philippine time).</p>
    @include('client-side.appointments.partials.document-verification', ['document' => $payment])
</section>
