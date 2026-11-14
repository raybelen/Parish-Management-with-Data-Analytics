@extends('admin.layout')
@section('title', 'Parish Revenue')
@section('content')
    <p class="admin-alert border-sky-200 bg-sky-50 text-sm text-sky-950"><strong>Test payments</strong> Test payments are included in balances and reports. They demonstrate the payment process without using real money.</p>
    <p class="max-w-3xl text-sm leading-6 text-muted">Monitor service payments, individual donations, Mass collections, refunds, and unpaid fees.@can('donations.view') <a class="underline" href="{{ route('admin.donations.index') }}">View donations</a>.@endcan</p>
    <form method="GET" class="admin-card grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-admin.field label="From" name="from" type="date" :value="$filters['from']" required />
        <x-admin.field label="To" name="to" type="date" :value="$filters['to']" required />
        <x-admin.field label="Service" name="service"><select name="service" class="admin-input"><option value="">All services</option>@foreach($services as $slug => $name)<option value="{{ $slug }}" @selected(($filters['service'] ?? '') === $slug)>{{ $name }}</option>@endforeach</select></x-admin.field>
        <x-admin.field label="Payment method" name="method"><select name="method" class="admin-input"><option value="">All methods</option>@foreach(['cash', 'bank_transfer', 'gcash', 'check', 'other', 'xendit_sandbox'] as $method)<option value="{{ $method }}" @selected(($filters['method'] ?? '') === $method)>{{ $method === 'xendit_sandbox' ? 'Online Payment' : str($method)->headline() }}</option>@endforeach</select></x-admin.field>
        <button class="admin-button">Apply filters</button>
    </form>
    @include('admin.partials.revenue-overview')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach(['received' => 'Payments received', 'refunded' => 'Refunds issued', 'net' => 'Service payments after refunds'] as $key => $label)
            <section class="admin-card"><h2 class="font-sans text-sm font-medium text-muted">{{ $label }}</h2><p class="mt-3 text-2xl font-semibold">₱{{ number_format($totals[$key] / 100, 2) }}</p><p class="mt-2 text-xs text-muted">Selected payment dates and filters</p></section>
        @endforeach
        <section class="admin-card"><h2 class="font-sans text-sm font-medium text-muted">Unpaid fees</h2><p class="mt-3 text-2xl font-semibold">₱{{ number_format($outstandingTotal / 100, 2) }}</p><p class="mt-2 text-xs text-muted">All dates and methods · selected service</p></section>
    </div>
    <p class="text-sm leading-6 text-muted">Service totals show payments received minus refunds during the selected dates, including test payments. Refunds may be for older payments. Unpaid fees and incomplete payments are not included.</p>
    <section class="admin-card">
        <h2 class="text-2xl">Collections by service</h2>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">@forelse($byService as $row)<div class="rounded-lg bg-slate-50 p-4"><p class="text-sm text-muted">{{ $services[$row->service_type] ?? str($row->service_type)->headline() }}</p><p class="mt-2 text-xl font-semibold">₱{{ number_format($row->net_cents / 100, 2) }}</p></div>@empty<p class="text-sm text-muted">No collections match these filters.</p>@endforelse</div>
    </section>
    <section class="admin-card">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-2xl">Payments and refunds</h2>@can('reports.view')<a class="admin-secondary" href="{{ route('admin.revenue.index', [...$filters, 'export' => 'collections']) }}">Download payment report (CSV)</a>@endcan</div>
        <p class="mt-2 text-sm text-muted">Record payments and refunds on the original appointment. Each entry appears here automatically.</p>
        <div class="mt-5 overflow-x-auto"><table class="admin-table"><thead><tr><th>Date</th><th>Appointment / service</th><th>Entry</th><th>Amount</th><th>Method / reference</th><th>Payment status</th><th>Recorded by</th><th>Statement</th></tr></thead><tbody>
            @forelse($payments as $payment)
                <tr><td class="whitespace-nowrap">{{ $payment->paid_at->format('M j, Y g:i A') }}</td><td>@can('appointments.view')<a class="underline" href="{{ route('admin.appointments.show', $payment->appointment) }}#appointment-payments">{{ $payment->appointment->reference_number }}</a>@else{{ $payment->appointment->reference_number }}@endcan<br><span class="text-muted">{{ $services[$payment->appointment->service_type] ?? str($payment->appointment->service_type)->headline() }}</span></td><td>{{ ucfirst($payment->kind) }} #{{ $payment->id }}</td><td class="whitespace-nowrap">{{ $payment->kind === 'refund' ? '−' : '' }}₱{{ number_format($payment->amount_cents / 100, 2) }}</td><td>{{ $payment->method === 'xendit_sandbox' ? 'Online Payment' : str($payment->method)->headline() }}<br>{{ $payment->reference ?: 'No reference recorded' }}</td><td>{{ $payment->kind === 'refund' ? 'Refund recorded' : 'Paid' }}</td><td>{{ $payment->recordedBy?->name ?? 'System or older record' }}</td><td><a class="underline" href="{{ route('admin.appointments.billing', $payment->appointment) }}" target="_blank" rel="noopener">View statement<span class="sr-only"> for {{ $payment->appointment->reference_number }}</span></a></td></tr>
            @empty<tr><td colspan="8" class="text-muted">No payments or refunds match these filters.</td></tr>@endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $payments->links() }}</div>
    </section>
    <section class="admin-card">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-2xl">Unpaid fees</h2>@can('reports.view')<a class="admin-secondary" href="{{ route('admin.revenue.index', [...$filters, 'export' => 'outstanding']) }}">Download unpaid fees report (CSV)</a>@endcan</div>
        <p class="mt-2 text-sm text-muted">Amounts still owed for the selected service, across all dates and payment methods. Appointments can be confirmed before payment.</p>
        <div class="mt-5 overflow-x-auto"><table class="admin-table"><thead><tr><th>Appointment</th><th>Client</th><th>Service</th><th>Balance</th><th>Statement</th></tr></thead><tbody>
            @forelse($outstanding as $appointment)<tr><td>{{ $appointment->reference_number }}</td><td>{{ $appointment->client_full_name }}</td><td>{{ $services[$appointment->service_type] ?? str($appointment->service_type)->headline() }}</td><td>₱{{ number_format($appointment->outstanding_cents / 100, 2) }}</td><td><a class="underline" href="{{ route('admin.appointments.billing', $appointment) }}" target="_blank" rel="noopener">View statement<span class="sr-only"> for {{ $appointment->reference_number }}</span></a></td></tr>@empty<tr><td colspan="5" class="text-muted">No unpaid fees for this service.</td></tr>@endforelse
        </tbody></table></div><div class="mt-4">{{ $outstanding->links() }}</div>
    </section>
    <section class="admin-card">
        <h2 class="text-2xl">Online payment attempts</h2>
        <p class="mt-2 text-sm text-muted">Online payment attempts started during the selected dates. Only completed payments count toward totals. Choosing an office payment method hides online attempts.</p>
        <div class="mt-5 overflow-x-auto"><table class="admin-table"><thead><tr><th>Created / appointment</th><th>Status</th><th>Amount</th><th>Payment method / type</th><th>Payment details</th></tr></thead><tbody>
            @forelse($transactions as $transaction)
                <tr><td>{{ $transaction->created_at->format('M j, Y g:i A') }}<br>{{ $transaction->appointment->reference_number }}</td><td>{{ str($transaction->status)->headline() }}</td><td>{{ $transaction->currency }} {{ number_format($transaction->amount_cents / 100, 2) }}</td><td>{{ ['PH_GCASH' => 'GCash', 'PH_PAYMAYA' => 'Maya', 'XENDIT_SANDBOX' => 'Xendit Sandbox'][$transaction->payment_method] ?? ($transaction->payment_method ?: 'Not provided') }}<br>{{ $transaction->environment === 'sandbox' ? 'Test payment' : ucfirst($transaction->environment) }}</td><td class="break-all">Reference: {{ $transaction->reference_id }}<br>Checkout ID: {{ $transaction->provider_session_id ?: 'Not created' }}<br>Payment: {{ $transaction->provider_payment_id ?: 'Not received' }}<br>Paid at: {{ $transaction->paid_at?->format('M j, Y g:i A') ?: 'Not paid' }}<br>Payment record: {{ $transaction->ledger_payment_id ? '#'.$transaction->ledger_payment_id : 'No payment recorded' }}</td></tr>
            @empty<tr><td colspan="5" class="text-muted">No online payment attempts match these filters.</td></tr>@endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $transactions->links() }}</div>
    </section>
    <section class="admin-card border-amber-200 bg-amber-50">
        <h2 class="text-2xl">Test payments included in these totals</h2>
        <p class="mt-2 text-sm leading-6">Test payments: ₱{{ number_format($sandboxTotals['received'] / 100, 2) }} · Test refunds: ₱{{ number_format($sandboxTotals['refunded'] / 100, 2) }}. These amounts are already included above; do not add them again. The selected filters also apply here.</p>
    </section>
@endsection
