@extends('admin.layout')
@section('title', 'Priest management')
@section('actions')
    <a class="admin-secondary" href="{{ route('admin.calendar.index') }}">Calendar & schedules</a>
@endsection
@section('content')
    <section class="admin-card grid gap-5">
        <div>
            <h2 class="text-2xl">Fixed three-priest rotation</h2>
            <p class="mt-2 text-sm text-muted">The next appointment anchors Priest 1. The daily Priest 1 → Priest 2 → Priest 3 sequence never advances because of unexpected activities or substitutions.</p>
        </div>
        @if($nextAppointment)
            <p class="rounded-xl border border-navy/10 bg-navy/[0.02] p-4 text-sm"><span class="font-semibold">Rotation anchor:</span> {{ $nextAppointment->starts_at->format('F j, Y \a\t g:i A') }} · {{ ucfirst($nextAppointment->service_type) }} · {{ $nextAppointment->reference_number }}@if($anchorDate) · anchored to {{ $nextAppointment->personnel?->name ?? 'Priest 1' }}@endif</p>
        @else
            <p class="admin-alert border-amber-300 bg-amber-50 text-amber-950">Add a future appointment before saving the rotation.</p>
        @endif
        <form method="POST" action="{{ route('admin.priests.rotation.update') }}" class="grid gap-4 sm:grid-cols-3">
            @csrf @method('PUT')
            @foreach([1, 2, 3] as $position)
                <label class="grid gap-1 text-sm">Priest {{ $position }}
                    <select class="admin-input" name="priests[{{ $position }}]" required>
                        <option value="">Choose personnel</option>
                        @foreach($personnel as $person)
                            <option value="{{ $person->id }}" @selected((string) old('priests.'.$position, $rotationPriests->get($position)?->id) === (string) $person->id)>{{ $person->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
            <button class="admin-button justify-self-start sm:col-span-3" @disabled(!$nextAppointment)>Save rotation and assign upcoming activities</button>
        </form>
    </section>

    <section class="admin-card grid gap-5">
        <div><h2 class="text-2xl">Priest unavailability</h2><p class="mt-2 text-sm text-muted">Add one whole day or a consecutive date range. Any affected activities are reassigned when another priest is available.</p></div>
        <form method="POST" action="{{ route('admin.priests.unavailabilities.store') }}" class="grid gap-5">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <label class="grid gap-1 text-sm"><span class="font-medium">Priest</span><select class="admin-input" name="personnel_id" required><option value="">Choose priest</option>@foreach($rotationPriests as $position => $priest)<option value="{{ $priest->id }}" @selected((string) old('personnel_id') === (string) $priest->id)>Priest {{ $position }} · {{ $priest->name }}</option>@endforeach</select></label>
                <label class="grid gap-1 text-sm"><span class="font-medium">Reason <span class="font-normal text-muted">(optional)</span></span><input class="admin-input" name="reason" maxlength="255" placeholder="For example, retreat or leave" value="{{ old('reason') }}"></label>
            </div>
            <fieldset class="grid gap-3 rounded-xl border border-navy/10 bg-navy/[0.02] p-4 sm:p-5">
                <legend class="px-1 text-sm font-semibold">Unavailable dates</legend>
                <div class="grid items-end gap-4 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] xl:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)_auto]">
                    <label class="grid gap-1 text-sm"><span class="font-medium">From</span><input class="admin-input" name="unavailable_from" type="date" min="{{ now()->toDateString() }}" value="{{ old('unavailable_from') }}" required></label>
                    <span class="hidden pb-3 text-sm text-muted sm:block" aria-hidden="true">to</span>
                    <label class="grid gap-1 text-sm"><span class="font-medium">Through <span class="font-normal text-muted">(optional)</span></span><input class="admin-input" name="unavailable_through" type="date" min="{{ old('unavailable_from', now()->toDateString()) }}" value="{{ old('unavailable_through') }}"></label>
                    <button class="admin-button sm:col-span-3 sm:justify-self-end xl:col-span-1">Save unavailable dates</button>
                </div>
                <p class="text-xs leading-5 text-muted">Leave the Through date blank to record only the From date.</p>
            </fieldset>
        </form>
        <div class="grid gap-3">
            @forelse($unavailabilities as $unavailability)
                <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                    <p><span class="font-semibold">Unavailable:</span> {{ $unavailability->personnel->name }} · {{ $unavailability->unavailable_on->format('l, F j, Y') }}@if($unavailability->reason) · {{ $unavailability->reason }}@endif</p>
                    <form method="POST" action="{{ route('admin.priests.unavailabilities.destroy', $unavailability) }}">@csrf @method('DELETE')<button class="admin-secondary">Remove</button></form>
                </article>
            @empty
                <p class="text-sm text-muted">No upcoming unavailable dates recorded.</p>
            @endforelse
        </div>
    </section>

    <section class="admin-card min-w-0">
        <div class="mb-5"><h2 class="text-2xl">Priest activity schedule</h2><p class="mt-2 text-sm text-muted">Showing {{ $from->format('F j') }}–{{ $through->format('F j, Y') }} of {{ $rangeStart->format('F j') }}–{{ $rangeThrough->format('F j, Y') }} · Every priest-required activity lasts one hour.</p></div>
        <div class="grid gap-3 lg:hidden">
            @forelse($scheduleRows as $row)
                <article class="rounded-xl border border-navy/10 p-4">
                    <p class="font-semibold">{{ $row['title'] }}</p><p class="mt-1 text-sm">{{ $row['starts_at']->format('l, F j, Y · g:i A') }}</p><p class="mt-1 text-sm">{{ $row['activity_type'] }} · {{ $row['personnel'] ?? 'Needs reassignment' }}</p>
                    <x-admin.priest-assignment-status :status="$row['assignment_status']" :regular="$row['regular_personnel'] ?? null" />
                </article>
            @empty
                <p class="rounded-xl border border-dashed border-navy/15 p-5 text-sm text-muted">No priest-required activities in this period.</p>
            @endforelse
        </div>
        <div class="hidden overflow-x-auto lg:block"><table class="admin-table"><thead><tr><th>Date</th><th>Day</th><th>Time</th><th>Activity type</th><th>Activity</th><th>Assigned priest</th><th>Status</th></tr></thead><tbody>
            @forelse($scheduleRows as $row)<tr><td>{{ $row['starts_at']->format('M j, Y') }}</td><td>{{ $row['starts_at']->format('l') }}</td><td>{{ $row['starts_at']->format('g:i A') }}</td><td>{{ $row['activity_type'] }}</td><td>{{ $row['title'] }}</td><td>{{ $row['personnel'] ?? 'Unassigned' }}</td><td><x-admin.priest-assignment-status :status="$row['assignment_status']" :regular="$row['regular_personnel'] ?? null" /></td></tr>@empty<tr><td colspan="7" class="text-center text-muted">No priest-required activities in this period.</td></tr>@endforelse
        </tbody></table></div>
        @if($schedulePeriods->hasPages())
            <nav class="mt-6 flex flex-col gap-3 border-t border-navy/10 pt-5 sm:flex-row sm:items-center sm:justify-between" aria-label="Priest activity schedule pages">
                <p class="text-sm text-muted">Period {{ $schedulePeriods->currentPage() }} of {{ $schedulePeriods->lastPage() }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    @if($schedulePeriods->onFirstPage())
                        <span class="admin-secondary min-h-10 cursor-not-allowed px-3 py-2 opacity-50" aria-disabled="true">Previous</span>
                    @else
                        <a class="admin-secondary min-h-10 px-3 py-2" href="{{ $schedulePeriods->previousPageUrl() }}" rel="prev">Previous</a>
                    @endif
                    @foreach($schedulePeriods->getUrlRange(1, $schedulePeriods->lastPage()) as $page => $url)
                        @if($page === $schedulePeriods->currentPage())
                            <span class="inline-flex size-10 items-center justify-center rounded-xl bg-navy text-sm font-semibold text-white" aria-current="page" aria-label="Page {{ $page }}">{{ $page }}</span>
                        @else
                            <a class="admin-secondary size-10 min-h-10 px-0 py-0" href="{{ $url }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                    @if($schedulePeriods->hasMorePages())
                        <a class="admin-secondary min-h-10 px-3 py-2" href="{{ $schedulePeriods->nextPageUrl() }}" rel="next">Next</a>
                    @else
                        <span class="admin-secondary min-h-10 cursor-not-allowed px-3 py-2 opacity-50" aria-disabled="true">Next</span>
                    @endif
                </div>
            </nav>
        @endif
    </section>
@endsection
