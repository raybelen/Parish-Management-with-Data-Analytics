@extends('client-side.layouts.parish')

@section('content')
    @php($statusLabel = match ($booking->status) { 'unverified' => 'Awaiting email verification', 'pending' => 'Pending staff approval', 'confirmed' => 'Approved', 'expired' => 'Expired', 'cancelled' => 'Canceled', default => str($booking->status)->headline() })
    <section class="section-wrap max-w-3xl py-10 sm:py-16">
        <a href="{{ route('facilities.index') }}" class="inline-flex min-h-11 items-center gap-2 text-sm text-muted hover:text-navy">&larr; Back to facilities</a>
        @if(session('status'))<p role="status" class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm leading-6 text-emerald-950">{{ session('status') }}</p>@endif
        <article class="mt-6 grid gap-6 rounded-2xl border border-navy/15 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="section-label text-muted">Facility request</p><h1 class="mt-3 text-3xl">{{ $booking->facility->name }}</h1></div><span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold">{{ $statusLabel }}</span></div>
            <dl class="grid gap-4 border-y border-navy/10 py-5 text-sm sm:grid-cols-2">
                <div><dt class="text-muted">Reference</dt><dd class="mt-1 break-all font-semibold">{{ $booking->reference }}</dd></div>
                <div><dt class="text-muted">Organizer</dt><dd class="mt-1 font-semibold">{{ $booking->organizer }}</dd></div>
                <div><dt class="text-muted">Activity</dt><dd class="mt-1">{{ $booking->title }} / {{ $booking->activity }}</dd></div>
                <div><dt class="text-muted">Attendees</dt><dd class="mt-1">{{ number_format($booking->attendees) }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-muted">Schedule</dt><dd class="mt-1">{{ $booking->starts_at->format('F j, Y g:i A') }}–{{ $booking->ends_at->format('g:i A') }}</dd></div>
                @if($booking->expires_at && in_array($booking->status, ['unverified', 'pending'], true))<div class="sm:col-span-2"><dt class="text-muted">Deadline</dt><dd class="mt-1">{{ $booking->expires_at->format('F j, Y g:i A') }}</dd></div>@endif
            </dl>
            @if($booking->cancellation_reason)<p class="rounded-xl bg-slate-50 p-4 text-sm text-muted">{{ $booking->cancellation_reason }}</p>@endif
            @if(!$booking->cancelled_at && $booking->ends_at->isFuture())
                <details><summary class="cursor-pointer text-sm font-semibold text-red-700">Cancel this request</summary><form method="POST" action="{{ $cancelUrl }}" class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">@csrf @method('DELETE')<label class="grid gap-2 text-sm">Cancellation reason<input class="admin-input" name="reason" maxlength="1000" required></label><button class="admin-secondary">Confirm cancellation</button></form></details>
            @endif
        </article>
    </section>
@endsection
