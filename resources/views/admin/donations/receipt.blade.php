<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $donation->isMassCollection() ? 'Mass collection receipt' : 'Donation receipt' }} · {{ $donation->receipt_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 p-4 font-sans text-navy sm:p-8 print:bg-white print:p-0">
    <main class="mx-auto max-w-3xl rounded-xl border border-navy/15 bg-white p-5 sm:p-10 print:max-w-none print:border-0 print:p-0">
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-navy/15 pb-6">
            <div><p class="text-lg">{{ $parishName }}</p><h1 class="mt-2 text-3xl">{{ $donation->isMassCollection() ? 'Mass collection receipt' : 'Donation receipt' }}</h1></div>
            <button type="button" data-print-billing class="rounded-lg bg-navy px-5 py-3 text-sm font-semibold text-white print:hidden">Print / Save PDF</button>
        </div>
        @if($donation->source === 'synthetic')<p class="mt-5 text-lg font-bold">Test transaction</p>@endif
        @if($donation->status === 'voided')<p class="mt-5 border-2 border-red-800 p-4 text-2xl font-bold text-red-800">CANCELED — This receipt is no longer valid.</p>@endif
        <dl class="mt-6 grid gap-5 sm:grid-cols-2 print:grid-cols-2">
            <div class="min-w-0"><dt class="text-sm text-muted">Receipt number</dt><dd class="mt-1 break-all font-semibold">{{ $donation->receipt_number }}</dd></div>
            <div><dt class="text-sm text-muted">Date received</dt><dd class="mt-1">{{ $donation->donated_on->format('F j, Y') }}</dd></div>
            <div><dt class="text-sm text-muted">Received from</dt><dd class="mt-1 break-words font-semibold">{{ $donation->donorLabel() }}</dd></div>
            <div><dt class="text-sm text-muted">Category</dt><dd class="mt-1">{{ \App\Models\Donation::CATEGORIES[$donation->category] }}</dd></div>
            <div><dt class="text-sm text-muted">Payment method</dt><dd class="mt-1">{{ \App\Models\Donation::METHODS[$donation->method] }}</dd></div>
            <div><dt class="text-sm text-muted">Payment reference</dt><dd class="mt-1 break-words">{{ $donation->reference ?: '—' }}</dd></div>
        </dl>
        @if($donation->isMassCollection())@include('admin.donations.mass-details', ['mass' => $donation])@endif
        @if($donation->massCollection)<p class="mt-5 text-sm">This gift is included in Mass collection {{ $donation->massCollection->receipt_number }} ({{ $donation->massCollection->church }}, {{ $donation->massCollection->mass_time }}). It is not an additional collection.</p>@endif
        <div class="mt-7 border-y border-navy/15 py-6"><p class="text-sm text-muted">{{ $donation->status === 'voided' ? 'Canceled amount' : 'Amount received' }} (PHP)</p><p class="mt-2 text-4xl font-semibold tabular-nums">₱{{ number_format($donation->amount_cents / 100, 2) }}</p></div>
        <p class="mt-6 text-sm">Recorded by: {{ $donation->recordedBy?->name ?? 'Parish office' }}</p>
        <p class="mt-2 text-sm">Version {{ $donation->version }} · Printed {{ now(config('office.timezone'))->format('M j, Y g:i A') }} (Philippine time)</p>
        @if($donation->version > 1)<p class="mt-2 text-sm font-semibold">This version replaces earlier copies of this receipt.</p>@endif
        <p class="mt-7 border-t border-navy/15 pt-5 text-xs leading-5 text-muted">Keep this receipt as a record of your donation. Use the receipt number when contacting the parish office. Thank you for your support.</p>
    </main>
</body>
</html>
