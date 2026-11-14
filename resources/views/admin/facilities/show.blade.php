@extends('admin.layout')
@section('title', $facility->name)
@section('actions')
    <a class="admin-secondary" href="{{ route('admin.facilities.index') }}">All facilities</a>
    @can('facilities.manage')<a class="admin-button" href="{{ route('admin.facilities.edit', $facility) }}">Edit facility</a>@endcan
@endsection
@section('content')
    <section class="admin-card grid gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-2xl">Facility overview</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold">{{ $facility->active ? 'Active' : 'Inactive' }}</span></div>
        @if($facility->location)<p class="text-sm text-muted">{{ $facility->location }}</p>@endif
        @if($facility->description)<p class="whitespace-pre-line text-sm">{{ $facility->description }}</p>@endif
        <dl class="grid gap-4 sm:grid-cols-2 text-sm">
            <div><dt class="text-muted">Capacity</dt><dd class="mt-1 font-semibold">{{ number_format($facility->capacity) }} people</dd></div>
            <div><dt class="text-muted">Permitted activities</dt><dd class="mt-1">{{ implode(', ', $facility->permitted_activities) }}</dd></div>
        </dl>
        <details><summary class="cursor-pointer text-sm font-semibold">Weekly opening hours</summary><dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 text-sm">@foreach(\App\Models\Facility::DAYS as $day => $label)<div><dt class="text-muted">{{ $label }}</dt><dd>@if($facility->opening_hours[$day]['open'] ?? false){{ $facility->opening_hours[$day]['opens_at'] }} - {{ $facility->opening_hours[$day]['closes_at'] }}@else Closed @endif</dd></div>@endforeach</dl></details>
    </section>
    <section class="admin-card grid gap-5">
        <div><h2 class="text-2xl">Availability &amp; bookings</h2><p class="mt-2 text-sm text-muted">Times are in {{ config('office.timezone') }}. Availability reflects reservations and closures recorded for this facility.</p></div>
        <form method="GET" class="flex flex-wrap items-end gap-3"><x-admin.field label="View date" name="date" type="date" :value="$date->toDateString()" required /><button class="admin-secondary">Check availability</button></form>
        <div class="rounded-xl border border-navy/10 bg-navy/[0.02] p-4">
            <h3 class="font-sans text-sm font-semibold leading-6 tracking-normal">Available periods on {{ $date->format('F j, Y') }}</h3>
            <div class="mt-3 flex flex-wrap gap-2">@forelse($freePeriods as [$from, $until])<span class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ $from->format('g:i A') }} - {{ $until->format('g:i A') }}</span>@empty<p class="text-sm text-muted">{{ !$facility->active ? 'This facility is inactive.' : ($date->endOfDay()->isPast() ? 'Past dates cannot be reserved.' : (!($hours['open'] ?? false) ? 'Closed on this day.' : 'No available periods on this day.')) }}</p>@endforelse</div>
        </div>
        <div class="grid gap-4">
            @forelse($bookings as $booking)
                @php($statusLabel = match ($booking->status) { 'unverified' => 'Awaiting email verification', 'pending' => 'Pending approval', 'confirmed' => 'Reserved', 'expired' => 'Expired', 'cancelled' => 'Canceled', default => str($booking->status)->headline() })
                <article class="grid gap-3 rounded-xl border border-navy/15 p-4">
                    <div class="flex flex-wrap justify-between gap-3"><h3 class="font-sans text-sm font-semibold leading-6 tracking-normal">{{ $booking->title }} <span class="font-normal text-muted">#{{ $booking->id }}</span></h3><span class="text-sm font-semibold">{{ $booking->kind === 'closure' && !$booking->cancelled_at ? 'Closed' : $statusLabel }}</span></div>
                    <p class="text-sm">{{ $booking->starts_at->format('M j, Y g:i A') }} - {{ $booking->ends_at->format('M j, Y g:i A') }}</p>
                    @if($booking->kind === 'reservation')<p class="text-sm text-muted">{{ $booking->organizer }} / {{ $booking->activity }} / {{ $booking->attendees }} people</p>@endif
                    @if($booking->cancelled_at)<p class="text-sm text-muted">Cancellation reason: {{ $booking->cancellation_reason }}</p>@endif
                    @if($booking->reference)<p class="text-sm">Reference: {{ $booking->reference }} / Contact: {{ $booking->contact_email }}</p>@endif
                    @can('facilities.manage')
                        @if($booking->status === 'pending' && !$booking->cancelled_at)<form method="POST" action="{{ route('admin.facilities.bookings.update', [$facility, $booking]) }}">@csrf @method('PATCH')<button class="admin-button">Approve request</button></form>@endif
                        @if(!$booking->cancelled_at && $booking->ends_at->isFuture())
                            <details><summary class="cursor-pointer text-sm font-semibold text-red-700">Cancel {{ $booking->kind }}</summary><form method="POST" action="{{ route('admin.facilities.bookings.destroy', [$facility, $booking]) }}" class="mt-3 grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">@csrf @method('DELETE')<x-admin.field label="Cancellation reason" name="reason" maxlength="1000" required /><button class="admin-secondary">Confirm cancellation</button></form></details>
                        @endif
                    @endcan
                </article>
            @empty<p class="text-sm text-muted">No reservations or closures recorded on this day.</p>@endforelse
        </div>
        {{ $bookings->links() }}
    </section>
    <section class="admin-card grid gap-4">
        <div><h2 class="text-2xl">Upcoming reservations &amp; closures</h2><p class="mt-2 text-sm text-muted">Open a date to review or cancel a booking. To reschedule, cancel the original reservation and record the new time.</p></div>
        @forelse($upcoming as $booking)
            <a class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-navy/10 p-4 text-sm hover:bg-ivory/50" href="{{ route('admin.facilities.show', ['facility' => $facility, 'date' => $booking->starts_at->toDateString()]) }}">
                <span><strong>{{ $booking->title }}</strong> / {{ $booking->kind === 'closure' ? 'Closure' : 'Reservation' }} #{{ $booking->id }}</span>
                <span>{{ $booking->starts_at->format('M j, Y g:i A') }} - {{ $booking->ends_at->format('M j, Y g:i A') }}</span>
            </a>
        @empty<p class="text-sm text-muted">No upcoming reservations or closures.</p>@endforelse
        {{ $upcoming->links() }}
    </section>
    @can('facilities.manage')
        <div class="grid items-start gap-5 xl:grid-cols-2">
            <section class="admin-card grid gap-5">
                <div><h2 class="text-2xl">Record a reservation</h2><p class="mt-2 text-sm text-muted">Include setup and cleanup time in the reserved period. Facility reservations have no fee.</p></div>
                @if($facility->active)
                    <form method="POST" action="{{ route('admin.facilities.bookings.store', $facility) }}" class="grid gap-4">
                        @csrf <input type="hidden" name="kind" value="reservation">
                        <x-admin.field label="Reservation title" name="title" maxlength="150" required />
                        <x-admin.field label="Organizer / group" name="organizer" maxlength="150" required />
                        <label class="grid gap-2 text-sm">Activity<select name="activity" class="admin-input" required><option value="">Choose an activity</option>@foreach($facility->permitted_activities as $activity)<option @selected(old('activity') === $activity)>{{ $activity }}</option>@endforeach</select></label>
                        <x-admin.field label="Number of attendees" name="attendees" type="number" min="1" :max="$facility->capacity" required />
                        <x-admin.field label="Starts at" name="starts_at" type="datetime-local" :value="$date->format('Y-m-d').'T'.($hours['opens_at'] ?? '09:00')" required />
                        <x-admin.field label="Ends at" name="ends_at" type="datetime-local" :value="$date->format('Y-m-d').'T'.($hours['closes_at'] ?? '10:00')" required />
                        <button class="admin-button justify-self-start">Save reservation</button>
                    </form>
                @else<p class="text-sm text-muted">Activate this facility to accept reservations.</p>@endif
            </section>
            <section class="admin-card grid gap-5">
                <div><h2 class="text-2xl">Close a facility temporarily</h2><p class="mt-2 text-sm text-muted">Block maintenance, repairs, or other unavailable periods. Closures may span multiple days. Conflicting reservations must be canceled first.</p></div>
                <form method="POST" action="{{ route('admin.facilities.bookings.store', $facility) }}" class="grid gap-4">
                    @csrf <input type="hidden" name="kind" value="closure">
                    <x-admin.field label="Closure reason" name="title" maxlength="150" required />
                    <x-admin.field label="Closure starts" name="starts_at" type="datetime-local" required />
                    <x-admin.field label="Closure ends" name="ends_at" type="datetime-local" required />
                    <button class="admin-secondary justify-self-start">Save closure</button>
                </form>
            </section>
        </div>
    @endcan
@endsection
