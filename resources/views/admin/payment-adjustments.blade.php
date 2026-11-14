@extends('admin.layout')
@section('title', 'Financial approvals')
@section('content')
    @can('donations.view')<p class="text-sm leading-6 text-muted"><a class="underline" href="{{ route('admin.donations.index', ['review' => 'pending']) }}">Review pending donation corrections and voids</a>.</p>@endcan
    <p class="text-sm text-muted">Review refunds and fee adjustments requested by another person. Pending and rejected requests do not change fees or collections. Approval updates the payment records. Any return of funds must be arranged separately.</p>
    <nav class="flex flex-wrap gap-2" aria-label="Adjustment request status">
        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
            <a class="{{ $status === $value ? 'admin-button' : 'admin-secondary' }}" href="{{ route('admin.payment-adjustments.index', ['status' => $value]) }}" @if($status === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @forelse($adjustments as $adjustment)
        <section class="admin-card grid gap-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><a class="text-link font-semibold" href="{{ route('admin.appointments.show', $adjustment->appointment) }}#appointment-payments">{{ $adjustment->appointment->reference_number }}</a><p class="mt-1 text-sm">{{ $adjustment->appointment->client_full_name }}</p></div>
                <p class="text-sm text-muted">Current fee: PHP {{ number_format($adjustment->appointment->fee_cents / 100, 2) }}<br>Current balance: PHP {{ number_format($adjustment->appointment->balanceCents() / 100, 2) }}</p>
            </div>
            @include('admin.partials.payment-adjustment', ['adjustment' => $adjustment])
        </section>
    @empty
        <p class="admin-card text-sm text-muted">No {{ $status }} requests.</p>
    @endforelse
    {{ $adjustments->links() }}
@endsection
