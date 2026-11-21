@extends('admin.layout')
@section('title', 'Facilities')
@section('actions')
    @can('facilities.manage')<a class="admin-button" href="{{ route('admin.facilities.create') }}">Add facility</a>@endcan
@endsection
@section('content')
    <section class="admin-card grid gap-4">
        <div><h2 class="text-2xl">Pending facility requests ({{ $pendingRequests->total() }})</h2><p class="mt-2 text-sm text-muted">Review the earliest approval deadlines first. Pending requests do not reserve time. Times are in {{ config('office.timezone') }}.</p></div>
        @forelse($pendingRequests as $booking)
            <article class="grid gap-3 border-b border-navy/10 pb-4 font-sans">
                <div class="grid gap-1">
                    <h3 class="font-sans text-base font-semibold leading-6 tracking-normal text-navy">{{ $booking->facility->name }} &mdash; {{ $booking->title }}</h3>
                    <p class="text-sm leading-6 text-muted">{{ $booking->organizer }} <span aria-hidden="true">&middot;</span> <span class="font-medium text-navy">{{ $booking->reference }}</span></p>
                </div>
                <p class="text-sm leading-6"><span class="font-medium">Requested schedule:</span> {{ $booking->starts_at->format('M j, Y g:i A') }} &ndash; {{ $booking->ends_at->format('g:i A') }}</p>
                <p class="text-sm leading-6 text-muted"><span class="font-medium text-navy">Submitted:</span> {{ $booking->created_at->format('M j, Y g:i A') }} <span aria-hidden="true">&middot;</span> <span class="font-medium text-navy">Approval deadline:</span> {{ $booking->expires_at?->format('M j, Y g:i A') ?? 'Not set' }}</p>
                <div class="flex flex-wrap gap-3">
                    <a class="admin-secondary" href="{{ route('admin.facilities.show', ['facility' => $booking->facility, 'date' => $booking->starts_at->toDateString()]) }}">Review request</a>
                    @can('facilities.manage')<form method="POST" action="{{ route('admin.facilities.bookings.update', [$booking->facility, $booking]) }}">@csrf @method('PATCH')<button class="admin-button">Approve request</button></form>@endcan
                </div>
            </article>
        @empty<p class="text-sm text-muted">No requests awaiting approval.</p>@endforelse
        {{ $pendingRequests->links() }}
    </section>
    <section class="admin-card grid gap-5">
        <div><h2 class="text-2xl">Parish halls &amp; rooms</h2><p class="mt-2 text-sm text-muted">Manage spaces, opening hours, reservations, and closures.</p></div>
        <form method="GET" class="grid items-end gap-4 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
            <x-admin.field label="Find a facility" name="search" :value="$filters['search'] ?? ''" maxlength="150" />
            <label class="grid gap-2 text-sm">Status<select name="status" class="admin-input"><option value="">All facilities</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></select></label>
            <button class="admin-secondary">Search</button>
        </form>
    </section>
    <div class="grid gap-5 lg:grid-cols-2">
        @forelse($facilities as $facility)
            <article class="admin-card grid content-start gap-4">
                <div class="flex flex-wrap items-start justify-between gap-3"><h2 class="text-xl"><a class="hover:underline" href="{{ route('admin.facilities.show', $facility) }}">{{ $facility->name }}</a></h2><span @class(['rounded-full px-3 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-800' => $facility->active, 'bg-slate-100 text-slate-600' => !$facility->active])>{{ $facility->active ? 'Active' : 'Inactive' }}</span></div>
                @if($facility->location)<p class="text-sm text-muted">{{ $facility->location }}</p>@endif
                <dl class="grid gap-4 text-sm"><div><dt class="text-muted">Capacity</dt><dd class="mt-1 font-semibold">{{ number_format($facility->capacity) }} people</dd></div></dl>
                <p class="text-sm"><span class="text-muted">Permitted activities:</span> {{ implode(', ', $facility->permitted_activities) }}</p>
                <a class="admin-secondary justify-self-start" href="{{ route('admin.facilities.show', $facility) }}">View availability &amp; reservations</a>
            </article>
        @empty
            <p class="admin-card text-sm text-muted lg:col-span-2">No facilities found. Add a hall or meeting room to begin managing availability.</p>
        @endforelse
    </div>
    {{ $facilities->links() }}
@endsection
