@extends('client-side.layouts.parish')

@section('content')
    <section class="section-wrap max-w-6xl py-10 sm:py-16">
        <a href="{{ route('appointments.index') }}" class="inline-flex min-h-11 items-center gap-2 text-sm text-muted hover:text-navy">&larr; Back to appointments</a>
        <header class="mt-5 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-2xl"><p class="section-label text-muted">Parish facilities</p><h1 class="mt-4 text-4xl leading-tight sm:text-5xl">A space for your next gathering</h1><p class="mt-4 text-base leading-7 text-muted">Choose a facility and send your preferred schedule for parish staff to review. Facility bookings have no fee.</p></div>
            <a href="{{ route('facilities.lookup.create') }}" class="admin-secondary shrink-0">Check request status</a>
        </header>

        @if(session('status'))
            <div role="status" class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5 sm:p-6">
                <div class="grid gap-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.16em] uppercase text-emerald-800">Request received</p>
                        <p class="mt-2 text-sm leading-6 text-emerald-950">{{ session('status') }}</p>
                        <p class="mt-2 text-xs leading-5 text-emerald-800">Use the reference and your contact email under Check request status to view updates later.</p>
                    </div>
                    @if(session('facility_reference'))
                        <div class="min-w-0 rounded-lg border border-emerald-300 bg-white/70 px-5 py-4 sm:min-w-72">
                            <p class="text-xs font-medium tracking-[0.16em] uppercase text-emerald-800">Facility reference</p>
                            <p class="mt-2 break-all font-mono text-lg font-semibold tracking-wide text-navy sm:text-xl">{{ session('facility_reference') }}</p>
                            <div class="mt-3"><x-parish.copy-reference :value="session('facility_reference')" /></div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
        @if($errors->any())
            <div role="alert" class="mt-6 rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-900">
                <p class="font-semibold">Please review your request.</p>
                <ul class="mt-2 list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="mt-9 grid gap-7">
            @forelse($facilities as $facility)
                <article class="overflow-hidden rounded-2xl border border-navy/15 bg-white shadow-sm">
                    <div class="grid lg:grid-cols-[minmax(0,1fr)_19rem]">
                        <div class="p-6 sm:p-8">
                            <h2 class="text-3xl leading-tight">{{ $facility->name }}</h2>
                            @if($facility->location)<p class="mt-2 text-sm text-muted">{{ $facility->location }}</p>@endif
                            <dl class="mt-6 grid gap-4 border-y border-navy/10 py-5 text-sm">
                                <div><dt class="text-muted">Capacity</dt><dd class="mt-1 font-semibold">Up to {{ number_format($facility->capacity) }} people</dd></div>
                            </dl>
                            @if($facility->description)<p class="mt-5 whitespace-pre-line text-sm leading-7 text-muted">{{ $facility->description }}</p>@endif
                            <p class="mt-5 text-sm font-medium">Permitted activities</p>
                            <div class="mt-2 flex flex-wrap gap-2">@foreach($facility->permitted_activities as $activity)<span class="rounded-lg bg-ivory/70 px-3 py-1.5 text-sm">{{ $activity }}</span>@endforeach</div>
                        </div>
                        <aside class="border-t border-navy/10 bg-slate-50/80 p-6 sm:p-8 lg:border-t-0 lg:border-l" aria-label="Opening hours for {{ $facility->name }}">
                            <h3 class="font-sans text-sm font-semibold tracking-normal">Weekly opening hours</h3>
                            <p class="mt-1 text-xs text-muted">{{ config('office.timezone') }}</p>
                            <dl class="mt-4 divide-y divide-navy/8 text-sm">
                                @foreach(\App\Models\Facility::DAYS as $day => $label)
                                    @php($hours = $facility->opening_hours[$day] ?? [])
                                    <div class="flex items-center justify-between gap-4 py-2.5">
                                        <dt class="text-muted">{{ $label }}</dt>
                                        <dd class="text-right {{ ($hours['open'] ?? false) ? 'font-medium text-navy' : 'text-muted' }}">{{ ($hours['open'] ?? false) ? date('g:i A', strtotime($hours['opens_at'])).' - '.date('g:i A', strtotime($hours['closes_at'])) : 'Closed' }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </aside>
                    </div>
                    <details class="group border-t border-navy/10" @if($errors->any()) open @endif>
                        <summary class="flex min-h-16 cursor-pointer list-none flex-wrap items-center justify-between gap-3 bg-ivory/30 px-6 py-4 marker:hidden sm:px-8 [&::-webkit-details-marker]:hidden">
                            <span class="text-sm text-muted">All reservations require staff approval.</span>
                            <span class="inline-flex min-h-11 items-center gap-3 rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white"><span class="group-open:hidden">Request this facility</span><span class="hidden group-open:inline">Close request form</span><x-parish.icon name="chevron-down" class="size-4 shrink-0 transition-transform group-open:rotate-180" /></span>
                        </summary>
                        <form method="POST" action="{{ route('facilities.bookings.store', $facility) }}" class="grid gap-7 p-6 sm:p-8" data-facility-booking-form>
                            @csrf
                            <fieldset class="grid gap-4 sm:grid-cols-2">
                                <legend class="mb-4 font-semibold">Your contact details</legend>
                                <label class="grid gap-2 text-sm">Your name or group<input class="admin-input" name="organizer" value="{{ old('organizer') }}" autocomplete="name" maxlength="150" required></label>
                                <label class="grid gap-2 text-sm">Contact email<input class="admin-input" name="contact_email" value="{{ old('contact_email') }}" type="email" autocomplete="email" maxlength="254" required></label>
                            </fieldset>
                            <fieldset class="grid gap-4 sm:grid-cols-2">
                                <legend class="mb-4 font-semibold">Activity &amp; schedule</legend>
                                <label class="grid gap-2 text-sm">Activity title<input class="admin-input" name="title" value="{{ old('title') }}" maxlength="150" placeholder="For example, parish council meeting" required></label>
                                <label class="grid gap-2 text-sm">Permitted activity<select class="admin-input" name="activity" required><option value="">Choose an activity</option>@foreach($facility->permitted_activities as $activity)<option @selected(old('activity') === $activity)>{{ $activity }}</option>@endforeach</select></label>
                                <label class="grid gap-2 text-sm">
                                    <span>Number of attendees</span>
                                    <input class="admin-input" name="attendees" value="{{ old('attendees') }}" type="number" min="1" required data-facility-attendees data-capacity="{{ $facility->capacity }}" aria-describedby="facility-attendees-feedback-{{ $facility->id }}">
                                    <span id="facility-attendees-feedback-{{ $facility->id }}" class="text-xs leading-5 text-muted" data-facility-attendees-feedback data-default-message="Maximum {{ $facility->capacity }} people" aria-live="polite">Maximum {{ $facility->capacity }} people</span>
                                </label>
                                <div class="hidden sm:block"></div>
                                <label class="grid gap-2 text-sm">Start date and time<input class="admin-input" name="starts_at" value="{{ old('starts_at') }}" type="datetime-local" min="{{ now()->addHours(config('facilities.approval_cutoff_hours'))->ceilMinute()->format('Y-m-d\TH:i') }}" required></label>
                                <label class="grid gap-2 text-sm">End date and time<input class="admin-input" name="ends_at" value="{{ old('ends_at') }}" type="datetime-local" required></label>
                                <p class="text-xs leading-6 text-muted sm:col-span-2">Choose a time within opening hours and include setup and cleanup. Start and end on the same day. Availability is checked when you submit.</p>
                            </fieldset>
                            <div class="flex justify-end border-t border-navy/10 pt-5">
                                <button class="admin-button w-full sm:w-auto">Submit for staff approval</button>
                            </div>
                        </form>
                    </details>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-navy/20 bg-white/60 p-8 text-center"><h2 class="text-2xl">No facilities available yet</h2><p class="mt-3 text-sm text-muted">Please contact the parish office for help arranging a space.</p><a class="mt-5 inline-flex min-h-11 items-center text-sm font-semibold underline underline-offset-4" href="{{ route('contact') }}">Contact the parish office</a></div>
            @endforelse
        </div>
        <div class="mt-6">{{ $facilities->links() }}</div>
    </section>
@endsection
