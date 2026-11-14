<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Billing statement · {{ $appointment->reference_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 p-4 font-sans text-navy sm:p-8 print:bg-white print:p-0">
    @if($manageUrl)<div hidden data-one-time-appointment-access data-manage-url="{{ $manageUrl }}"></div>@endif
    <main data-billing-document class="mx-auto max-w-4xl rounded-xl border border-navy/15 bg-white p-5 sm:p-10 print:max-w-none print:border-0 print:p-0">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-navy/15 pb-6">
            <div><p class="text-sm text-muted">{{ $parishName }}</p><h1 class="mt-2 text-3xl">Billing statement</h1></div>
            <button type="button" data-print-billing class="rounded-lg bg-navy px-5 py-3 text-sm font-semibold text-white print:hidden">Print / Save PDF</button>
        </div>
        @if($appointment->source === 'synthetic')<p class="mb-5 font-semibold">Test transaction</p>@endif
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-muted">Appointment reference</dt><dd class="mt-1 font-semibold">{{ $appointment->reference_number }}</dd></div>
            <div><dt class="text-muted">Prepared</dt><dd class="mt-1">{{ now()->format('M j, Y g:i A') }}</dd></div>
            <div><dt class="text-muted">Client</dt><dd class="mt-1">{{ $appointment->client_full_name }}</dd></div>
            <div><dt class="text-muted">Service</dt><dd class="mt-1">{{ $serviceLabel }}</dd></div>
        </dl>
        <div class="mt-7 flex flex-wrap items-center justify-between gap-4"><h2 class="text-2xl">Current charges and balance</h2><span class="rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold">{{ $appointment->billingStatusLabel() }}</span></div>
        <dl class="mt-4 grid grid-cols-3 gap-3">@foreach([($appointment->service_type === 'others' ? 'Voluntary offering' : 'Service fee') => $appointment->fee_cents, 'Paid after refunds' => $appointment->netPaidCents(), 'Balance' => $appointment->balanceCents()] as $label => $cents)<div class="rounded-lg border border-navy/10 p-3"><dt class="text-xs text-muted">{{ $label }}</dt><dd class="mt-2 text-lg font-semibold">₱{{ number_format($cents / 100, 2) }}</dd></div>@endforeach</dl>
        <p class="mt-4 text-sm leading-6 text-muted">The service fee reflects any adjustments or waiver recorded by the parish office. Payment can be made separately and does not block appointment confirmation.</p>
        @include('client-side.appointments.partials.billing-ledger', ['appointment' => $appointment])
        <nav aria-label="Billing documents" class="mt-6 flex flex-wrap gap-3 print:hidden">
            @foreach($appointment->invoices as $invoice)<a class="underline" href="#invoice-{{ $invoice->id }}">Invoice {{ $invoice->number }}</a>@endforeach
            @foreach($appointment->payments->where('kind', 'payment') as $payment)<a class="break-all underline" href="#receipt-{{ $payment->id }}">Payment receipt {{ $payment->receiptNumber() }}</a>@endforeach
        </nav>
        @if($appointment->invoices->isEmpty())<p class="mt-4 text-sm text-muted">No invoice has been issued yet. Contact the parish office to request one.</p>@endif
        <p class="mt-7 border-t border-navy/15 pt-5 text-xs leading-5 text-muted">This statement shows the current fee and acknowledges payments and refunds recorded by the parish as of the date shown. Keep your appointment reference when contacting the parish office.</p>
    </main>
    @foreach($appointment->invoices as $invoice)
        @include('client-side.appointments.partials.invoice', ['invoice' => $invoice])
    @endforeach
    @foreach($appointment->payments->where('kind', 'payment')->sortBy('id') as $payment)
        @include('client-side.appointments.partials.payment-receipt', ['payment' => $payment])
    @endforeach
</body>
</html>
