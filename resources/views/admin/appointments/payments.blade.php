<section id="appointment-payments" class="admin-card scroll-mt-28"><h2 class="mb-2 text-2xl">Optional payments & balance</h2><p class="mb-5 text-sm text-muted">Payment is not required to confirm or keep the appointment on the calendar. Record it whenever the parishioner is ready.</p><div class="mb-6 grid gap-3 sm:grid-cols-3">@foreach(['Fee' => $appointment->fee_cents, 'Paid after refunds' => $appointment->netPaidCents(), 'Balance' => $appointment->balanceCents()] as $label => $cents)<p class="rounded-lg bg-slate-50 p-4 text-sm">{{ $label }}<br><strong class="text-xl">₱{{ number_format($cents / 100, 2) }}</strong></p>@endforeach</div>
    @can('payments.manage')
        <details class="mb-5"><summary class="cursor-pointer text-sm font-semibold">Adjust fee / waive fee</summary><form class="mt-4 grid items-end gap-3 sm:grid-cols-3" method="POST" action="{{ route('admin.payments.fee', $appointment) }}">@csrf @method('PATCH')<input type="hidden" name="version" value="{{ $appointment->version }}"><x-admin.field label="Fee (PHP)" name="fee" type="number" step="0.01" min="0" :value="number_format($appointment->fee_cents / 100, 2, '.', '')" required /><x-admin.field label="Reason" name="reason" required /><button class="admin-secondary">Save fee</button></form></details>
        <form class="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-3" method="POST" action="{{ route('admin.payments.store', $appointment) }}" data-confirm="Save this payment or refund? Corrections are made with refunds; saved entries cannot be edited.">@csrf<input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
            <x-admin.field label="Entry type" name="kind"><select class="admin-input" name="kind"><option value="payment">Payment received</option><option value="refund">Refund issued</option></select></x-admin.field>
            <x-admin.field label="Amount (PHP)" name="amount" type="number" step="0.01" min="0.01" required />
            <x-admin.field label="Payment / refund date" name="paid_at" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" required />
            <x-admin.field label="Method" name="method"><select class="admin-input" name="method">@foreach(['cash','bank_transfer','gcash','check','other'] as $method)<option value="{{ $method }}">{{ \Illuminate\Support\Str::headline($method) }}</option>@endforeach</select></x-admin.field>
            <x-admin.field label="Transaction / receipt reference" name="reference" />
            <x-admin.field label="Original payment (refunds only)" name="original_payment_id"><select class="admin-input" name="original_payment_id"><option value="">Not applicable</option>@foreach($appointment->payments->where('kind', 'payment') as $payment)<option value="{{ $payment->id }}">#{{ $payment->id }} · ₱{{ number_format($payment->amount_cents / 100, 2) }} · {{ $payment->paid_at->format('M j') }}</option>@endforeach</select></x-admin.field>
            <x-admin.field label="Ledger note / reason" name="notes" required /><button class="admin-button">Record entry</button>
        </form>
    @endcan
    @if($appointment->paymentTransactions->isNotEmpty())
        <div class="mt-6 border-t border-navy/10 pt-6">
            <h3 class="text-lg font-semibold">Xendit Sandbox transactions</h3>
            <p class="mt-1 text-sm text-muted">Gateway status is updated only by verified sandbox webhooks. Browser redirects do not mark a payment as paid.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="admin-table">
                    <thead><tr><th>Created</th><th>Status</th><th>Amount</th><th>Sandbox references</th></tr></thead>
                    <tbody>
                        @foreach($appointment->paymentTransactions as $transaction)
                            <tr>
                                <td>{{ $transaction->created_at->format('M j, Y g:i A') }}</td>
                                <td>{{ str($transaction->status)->headline() }}</td>
                                <td>₱{{ number_format($transaction->amount_cents / 100, 2) }}</td>
                                <td class="break-all">{{ $transaction->provider_session_id ?: 'Session not created' }}<br>{{ $transaction->provider_payment_id }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    <div class="mt-6 overflow-x-auto"><table class="admin-table"><thead><tr><th>Date</th><th>Entry</th><th>Amount</th><th>Method / reference</th><th>Note</th></tr></thead><tbody>@forelse($appointment->payments as $payment)<tr><td>{{ $payment->paid_at->format('M j, Y g:i A') }}</td><td>{{ ucfirst($payment->kind) }} #{{ $payment->id }}</td><td>{{ $payment->kind === 'refund' ? '−' : '' }}₱{{ number_format($payment->amount_cents / 100, 2) }}</td><td>{{ $payment->method }}<br>{{ $payment->reference }}</td><td>{{ $payment->notes }}</td></tr>@empty<tr><td colspan="5" class="text-muted">No payments recorded. Completion and payment status are independent.</td></tr>@endforelse</tbody></table></div>
</section>
