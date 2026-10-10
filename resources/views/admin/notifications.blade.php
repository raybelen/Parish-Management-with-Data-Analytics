@extends('admin.layout')
@section('title', 'Office inbox')
@section('content')
    <p class="text-sm text-muted">Shared office notifications for new requests, status changes, upcoming services, missing physical documents and outstanding balances.</p>
    <div class="grid gap-3">@forelse($notifications as $notification)<section class="admin-card flex flex-wrap items-center justify-between gap-4"><div><p class="text-sm font-semibold">{{ $notification->message }}</p><p class="mt-2 text-xs text-muted">{{ $notification->created_at->format('M j, Y g:i A') }} · {{ $notification->read_at ? 'Acknowledged' : 'Needs attention' }}</p>@if($notification->appointment)<a class="text-link mt-2" href="{{ route('admin.appointments.show', $notification->appointment) }}">Open appointment</a>@endif</div>@unless($notification->read_at)<form method="POST" action="{{ route('admin.notifications.update', $notification) }}">@csrf @method('PATCH')<button class="admin-secondary">Acknowledge</button></form>@endunless</section>@empty<p class="admin-card text-sm text-muted">No office notifications yet.</p>@endforelse</div>{{ $notifications->links() }}
@endsection
