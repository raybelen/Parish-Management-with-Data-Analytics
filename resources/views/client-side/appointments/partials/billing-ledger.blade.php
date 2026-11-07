@if($appointment->netPaidCents() > $appointment->fee_cents)
    <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm">Overpayment: ₱{{ number_format(($appointment->netPaidCents() - $appointment->fee_cents) / 100, 2) }}. Please contact the parish office to arrange an adjustment or refund.</p>
@endif
<div class="mt-6 overflow-x-auto">
    <table class="w-full text-left text-sm">
        <caption class="mb-3 text-left font-semibold">Payment acknowledgments and refunds</caption>
        <thead class="border-b border-navy/15"><tr><th class="p-3">Date</th><th class="p-3">Entry</th><th class="p-3">Amount</th><th class="p-3">Method</th><th class="p-3">Reference</th></tr></thead>
        <tbody>
            @forelse($appointment->payments->sortBy('paid_at') as $payment)
                <tr class="border-b border-navy/10"><td class="whitespace-nowrap p-3">{{ $payment->paid_at->format('M j, Y g:i A') }}</td><td class="p-3">{{ ucfirst($payment->kind) }} #{{ $payment->id }}</td><td class="whitespace-nowrap p-3">{{ $payment->kind === 'refund' ? '−' : '' }}₱{{ number_format($payment->amount_cents / 100, 2) }}</td><td class="p-3">{{ str($payment->method)->headline() }}</td><td class="break-all p-3">{{ $payment->reference ?: 'Not recorded' }}</td></tr>
            @empty
                <tr><td colspan="5" class="p-3 text-muted">No payments recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($appointment->payments->contains(fn ($payment) => $payment->isSandbox()))
    <p class="mt-4 text-sm leading-6 text-muted">Demonstration statement: sandbox payments and demonstration refunds are included in the balance and history. No real money moves through the sandbox.</p>
@endif
