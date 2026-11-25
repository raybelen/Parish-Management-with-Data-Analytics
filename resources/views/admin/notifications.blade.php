@extends('admin.layout')
@section('title', 'Office inbox')
@section('content')
    <p class="text-sm text-muted">Shared office notifications for new requests, status changes, upcoming services, missing physical documents and outstanding balances.</p>
    <form method="GET" action="{{ route('admin.notifications.index') }}" class="admin-card grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto]">
        <x-admin.field label="Status" name="status">
            <select name="status" class="admin-input">
                @foreach (['' => 'All statuses', 'unread' => 'Needs attention', 'acknowledged' => 'Acknowledged'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-admin.field>
        <x-admin.field label="Type" name="type">
            <select name="type" class="admin-input">
                @foreach (['' => 'All types', 'appointment' => 'Appointments', 'facility' => 'Facility requests'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-admin.field>
        <div class="flex flex-wrap gap-2">
            <button class="admin-button">Apply filters</button>
            <a href="{{ route('admin.notifications.index') }}" class="admin-secondary">Clear</a>
        </div>
    </form>
    <div class="grid gap-3">@forelse($notifications as $notification)<section class="admin-card flex flex-wrap items-center justify-between gap-4"><div><p class="text-sm font-semibold">{{ $notification->message }}</p><p class="mt-2 text-xs text-muted">{{ $notification->created_at->format('M j, Y g:i A') }} · {{ $notification->read_at ? 'Acknowledged' : 'Needs attention' }}</p>@if($notification->appointment)<a class="text-link mt-2" href="{{ route('admin.appointments.show', $notification->appointment) }}">Open appointment</a>@endif
@if($booking = $notification->facilityBooking)<a class="text-link mt-2" href="{{ route('admin.facilities.show', ['facility' => $booking->facility, 'date' => $booking->starts_at->toDateString()]) }}">Review facility request</a>@endif</div>@unless($notification->read_at)<form method="POST" action="{{ route('admin.notifications.update', $notification) }}">@csrf @method('PATCH')<button class="admin-secondary">Acknowledge</button></form>@endunless</section>@empty<p class="admin-card text-sm text-muted">{{ array_filter($filters) ? 'No notifications match these filters. Try another selection or clear the filters.' : 'No office notifications yet.' }}</p>@endforelse</div>{{ $notifications->links() }}
@endsection
