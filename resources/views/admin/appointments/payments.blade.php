<section id="appointment-payments" class="admin-card scroll-mt-28"><h2 class="mb-2 text-2xl">Billing & payments</h2><p class="mb-5 text-sm text-muted">Fees are payable separately and do not block appointment confirmation. Confirmed payments appear in your payment history and update your balance.</p><div class="mb-6 grid gap-3 sm:grid-cols-3">@foreach([($appointment->service_type === 'others' ? 'Voluntary offering' : 'Fee') => $appointment->fee_cents, 'Paid after refunds' => $appointment->netPaidCents(), 'Balance' => $appointment->balanceCents()] as $label => $cents)<p class="rounded-lg bg-slate-50 p-4 text-sm">{{ $label }}<br><strong class="text-xl">₱{{ number_format($cents / 100, 2) }}</strong></p>@endforeach</div>
    <div class="mb-5 flex flex-wrap items-center gap-3"><span class="rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold">{{ $appointment->billingStatusLabel() }}</span><a class="admin-secondary" href="{{ route('admin.appointments.billing', $appointment) }}" target="_blank" rel="noopener">View / print billing statement</a><a class="admin-secondary" href="{{ route('admin.revenue.index') }}">Parish Revenue</a></div>
    @if($appointment->netPaidCents() > $appointment->fee_cents)<p class="mb-5 text-sm text-amber-900">Overpayment: ₱{{ number_format(($appointment->netPaidCents() - $appointment->fee_cents) / 100, 2) }}. Review the payment history before recording a refund.</p>@endif
    @can('payments.manage')
        <form class="mb-5 flex flex-wrap items-center gap-3" method="POST" action="{{ route('admin.invoices.store', $appointment) }}">
            @csrf
            <input type="hidden" name="fee_cents" value="{{ $appointment->fee_cents }}">
            <button class="admin-secondary">Issue / view invoice</button>
            <p class="text-sm text-muted">Saves the current approved fee. A changed fee creates a replacement invoice and retains the earlier copy.</p>
        </form>
        <p class="mb-4 text-sm text-muted">Payments are recorded immediately. Refunds and fee adjustments remain pending until a different super administrator approves them.</p>
        <details class="mb-5"><summary class="cursor-pointer text-sm font-semibold">Request fee adjustment / waiver</summary><form class="mt-4 grid items-end gap-3 sm:grid-cols-3" method="POST" action="{{ route('admin.payments.fee', $appointment) }}">@csrf @method('PATCH')<input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="version" value="{{ $appointment->version }}"><x-admin.field label="Requested fee (PHP)" name="fee" type="number" step="0.01" min="0" :value="number_format($appointment->fee_cents / 100, 2, '.', '')" required /><x-admin.field label="Reason" name="reason" required /><button class="admin-secondary">Request fee adjustment</button></form></details>
        <form class="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-3" method="POST" action="{{ route('admin.payments.store', $appointment) }}" data-confirm="Submit this entry? Payments are recorded immediately; refund requests require separate approval.">@csrf<input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
            <x-admin.field label="Entry type" name="kind"><select class="admin-input" name="kind"><option value="payment">Payment received</option><option value="refund">Request refund entry</option></select></x-admin.field>
            <x-admin.field label="Amount (PHP)" name="amount" type="number" step="0.01" min="0.01" required />
            <x-admin.field label="Payment / refund date" name="paid_at" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" required />
            <x-admin.field label="Method" name="method"><select class="admin-input" name="method">@foreach(['cash','bank_transfer','gcash','check','other'] as $method)<option value="{{ $method }}">{{ \Illuminate\Support\Str::headline($method) }}</option>@endforeach<option value="xendit_sandbox">Online Payment (refunds only)</option></select></x-admin.field>
            <x-admin.field label="Transaction / receipt reference" name="reference" />
            <x-admin.field label="Original payment (refunds only)" name="original_payment_id"><select class="admin-input" name="original_payment_id"><option value="">Not applicable</option>@foreach($appointment->payments->where('kind', 'payment') as $payment)<option value="{{ $payment->id }}">#{{ $payment->id }} · ₱{{ number_format($payment->amount_cents / 100, 2) }} · {{ $payment->paid_at->format('M j') }}</option>@endforeach</select></x-admin.field>
            <x-admin.field label="Payment note / reason" name="notes" required /><button class="admin-button">Submit entry</button>
        </form>
        <p class="mt-3 text-sm text-muted">Test refunds update payment records only.</p>
    @endcan
    @if($appointment->paymentAdjustments->isNotEmpty())
        <div class="mt-6 grid gap-4 border-t border-navy/10 pt-6">
            <h3 class="text-lg font-semibold">Refund and fee adjustment requests</h3>
            @foreach($appointment->paymentAdjustments as $adjustment)
                @include('admin.partials.payment-adjustment', ['adjustment' => $adjustment])
            @endforeach
        </div>
    @endif
    @if($appointment->paymentTransactions->isNotEmpty())
        <div class="mt-6 border-t border-navy/10 pt-6">
            <h3 class="text-lg font-semibold">Online payments</h3>
            <p class="mt-1 text-sm text-muted">Payments are recorded automatically once confirmed. Pending, failed, and expired payments are not included in the totals.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="admin-table">
                    <thead><tr><th>Created</th><th>Status</th><th>Amount</th><th>Payment method / type</th><th>Paid at</th><th>Payment references</th></tr></thead>
                    <tbody>
                        @foreach($appointment->paymentTransactions as $transaction)
                            <tr>
                                <td>{{ $transaction->created_at->format('M j, Y g:i A') }}</td>
                                <td>{{ str($transaction->status)->headline() }}</td>
                                <td>₱{{ number_format($transaction->amount_cents / 100, 2) }}</td>
                                <td>{{ ['PH_GCASH' => 'GCash', 'PH_PAYMAYA' => 'Maya', 'XENDIT_SANDBOX' => 'Xendit Sandbox'][$transaction->payment_method] ?? ($transaction->payment_method ?: 'Not provided') }}<br>{{ $transaction->environment === 'sandbox' ? 'Test transaction' : 'Live transaction' }}</td><td>{{ $transaction->paid_at?->format('M j, Y g:i A') ?: 'Not paid' }}</td><td class="break-all">Reference: {{ $transaction->reference_id }}<br>Checkout reference: {{ $transaction->provider_session_id ?: 'Not created' }}<br>Payment: {{ $transaction->provider_payment_id ?: 'Not received' }}<br>Payment record: {{ $transaction->ledger_payment_id ? '#'.$transaction->ledger_payment_id : 'No payment recorded' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    <div class="mt-6 overflow-x-auto"><table class="admin-table"><thead><tr><th>Date</th><th>Entry</th><th>Amount</th><th>Method / reference</th><th>Note</th></tr></thead><tbody>@forelse($appointment->payments as $payment)<tr><td>{{ $payment->paid_at->format('M j, Y g:i A') }}</td><td>{{ ucfirst($payment->kind) }} #{{ $payment->id }}</td><td>{{ $payment->kind === 'refund' ? '−' : '' }}₱{{ number_format($payment->amount_cents / 100, 2) }}</td><td>{{ $payment->method === 'xendit_sandbox' ? 'Online Payment' : str($payment->method)->headline() }}<br>{{ $payment->reference }}</td><td>{{ $payment->notes === 'Automatically confirmed by a verified Xendit Sandbox webhook.' ? 'Online test payment confirmed automatically.' : $payment->notes }}</td></tr>@empty<tr><td colspan="5" class="text-muted">No payments recorded. Completion and payment status are independent.</td></tr>@endforelse</tbody></table></div>
</section>
