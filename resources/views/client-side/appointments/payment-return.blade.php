@extends('client-side.layouts.parish')

@section('content')
    @php
        $status = $transaction?->status ?? 'failed';
        $heading = match ($status) {
            'paid' => 'Payment confirmed',
            'pending' => 'Payment confirmation is processing',
            'expired' => 'Payment session expired',
            default => 'Online payment unavailable',
        };
        $message = match ($status) {
            'paid' => 'Your payment has been confirmed and recorded.',
            'pending' => 'We are waiting for payment confirmation. This page will update automatically.',
            'expired' => 'No payment was recorded. Your appointment remains separate from payment and may still be handled at the parish office.',
            default => $error ?? 'No payment was recorded. You may pay at the parish office instead.',
        };
    @endphp

    <section class="min-h-[70vh] bg-[#eae5da] py-14 sm:py-20">
        <div class="section-wrap max-w-3xl">
            <div class="border border-navy/15 bg-ivory p-6 shadow-sm sm:p-10">
                <p class="section-label text-muted">Online payment · Test transaction</p>
                <h1 class="mt-5 text-4xl leading-tight sm:text-5xl">{{ $heading }}</h1>
                <p class="mt-5 text-base leading-8 text-muted">{{ $message }}</p>

                <dl class="mt-8 grid gap-4 border-y border-navy/10 py-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium tracking-[0.12em] uppercase text-muted">Appointment reference</dt>
                        <dd class="mt-2 font-mono text-lg font-semibold text-navy">{{ $appointment->reference_number }}</dd>
                    </div>
                    @if($transaction)
                        <div>
                            <dt class="text-xs font-medium tracking-[0.12em] uppercase text-muted">Amount</dt>
                            <dd class="mt-2 text-lg font-semibold text-navy">₱{{ number_format($transaction->amount_cents / 100, 2) }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    @if($transaction && $transaction->status === 'pending')
                        <a href="{{ route('appointments.payments.return', $transaction) }}" class="inline-flex min-h-12 items-center justify-center bg-gold px-6 py-3 text-sm font-medium text-navy transition-colors hover:bg-[#c9aa63]">Refresh payment status</a>
                    @endif
                    <a href="{{ route('appointments.lookup.create') }}" class="inline-flex min-h-12 items-center justify-center border border-navy/20 px-6 py-3 text-sm font-medium text-navy transition-colors hover:border-gold hover:bg-gold/10">Manage appointment</a>
                </div>

                <p class="mt-7 border-l-4 border-sky-700 bg-sky-50 px-4 py-3 text-sm leading-6 text-sky-950">Keep your appointment reference when contacting the parish office about this payment.</p>
            </div>
        </div>
    </section>

    @if($transaction?->status === 'pending')
        <script>
            window.setTimeout(() => window.location.reload(), 3000);
        </script>
    @endif
@endsection
