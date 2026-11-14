@extends('client-side.layouts.parish')

@section('content')
    <section class="section-wrap max-w-6xl py-10 sm:py-16">
        <a href="{{ route('appointments.index') }}" class="inline-flex min-h-11 items-center gap-2 text-sm text-muted hover:text-navy">&larr; Back to appointments</a>
        <header class="mt-5 max-w-2xl">
            <p class="section-label text-muted">Parish facilities</p>
            <h1 class="mt-4 text-4xl leading-tight sm:text-5xl">A space for your next gathering</h1>
            <p class="mt-4 text-base leading-7 text-muted">Choose a facility and send your preferred schedule for parish staff to review. Facility bookings have no fee.</p>
        </header>

        @if(session('status'))
            <p role="status" class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm leading-6 text-emerald-950">{{ session('status') }}</p>
        @endif
        @if($errors->any())
            <div role="alert" class="mt-6 rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-900">
                <p class="font-semibold">Please review your request.</p>
                <ul class="mt-2 list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if($bookings->isNotEmpty())
            <section class="mt-8 grid gap-4" aria-labelledby="your-requests">
                <div><h2 id="your-requests" class="text-2xl">Your facility requests</h2><p class="mt-1 text-sm leading-6 text-muted">Requests saved in this browser session. Keep your reference number for inquiries with the parish office.</p></div>
                @foreach($bookings as $booking)
                    @php($statusLabel = match ($booking->status) { 'unverified' => 'Check your email', 'pending' => 'Pending approval', 'confirmed' => 'Approved', 'expired' => 'Expired', 'cancelled' => 'Canceled', default => str($booking->status)->headline() })
                    <article class="grid gap-3 rounded-xl border border-navy/15 bg-white p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h3 class="font-sans text-base font-semibold tracking-normal">{{ $booking->facility->name }}</h3>
                            <span @class(['rounded-full px-3 py-1 text-xs font-semibold', 'bg-slate-100 text-muted' => $booking->cancelled_at, 'bg-amber-50 text-amber-900' => !$booking->cancelled_at && in_array($booking->status, ['unverified', 'pending'], true), 'bg-emerald-50 text-emerald-900' => !$booking->cancelled_at && $booking->status === 'confirmed'])>{{ $statusLabel }}</span>
                        </div>
                        <p class="text-sm">{{ $booking->starts_at->format('M j, Y g:i A') }} &ndash; {{ $booking->ends_at->format('g:i A') }}</p>
                        <p class="text-sm text-muted">Reference <span class="break-all font-medium text-navy">{{ $booking->reference }}</span></p>
                        @if($booking->cancelled_at)<p class="text-sm text-muted">{{ $booking->cancellation_reason }}</p>@endif
                    </article>
                @endforeach
            </section>
        @endif

        <section class="mt-8 rounded-2xl border border-navy/15 bg-white p-6 sm:p-8" aria-labelledby="recover-request">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.9fr)] lg:items-end">
                <div><h2 id="recover-request" class="text-2xl">Access an existing request</h2><p class="mt-2 text-sm leading-6 text-muted">Enter the reference and contact email used on the request. We will email a secure status link without revealing whether a request exists.</p></div>
                <form method="POST" action="{{ route('facilities.bookings.recover') }}" class="grid gap-3 sm:grid-cols-2">
                    @csrf
                    <label class="grid gap-2 text-sm">Reference<input class="admin-input" name="reference" value="{{ old('reference') }}" maxlength="40" placeholder="FAC-..." required></label>
                    <label class="grid gap-2 text-sm">Contact email<input class="admin-input" name="contact_email" value="{{ old('contact_email') }}" type="email" maxlength="254" required></label>
                    <button class="admin-secondary sm:col-span-2 sm:justify-self-end">Email secure link</button>
                </form>
            </div>
        </section>

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
                            <span class="inline-flex min-h-11 items-center gap-3 rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white"><span class="group-open:hidden">Request this facility</span><span class="hidden group-open:inline">Close request form</span><span aria-hidden="true" class="transition-transform group-open:rotate-180">&#8964;</span></span>
                        </summary>
                        <form method="POST" action="{{ route('facilities.bookings.store', $facility) }}" class="grid gap-7 p-6 sm:p-8">
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
                                <label class="grid gap-2 text-sm">Number of attendees<input class="admin-input" name="attendees" value="{{ old('attendees') }}" type="number" min="1" max="{{ $facility->capacity }}" required><span class="text-xs text-muted">Maximum {{ $facility->capacity }} people</span></label>
                                <div class="hidden sm:block"></div>
                                <label class="grid gap-2 text-sm">Start date and time<input class="admin-input" name="starts_at" value="{{ old('starts_at') }}" type="datetime-local" required></label>
                                <label class="grid gap-2 text-sm">End date and time<input class="admin-input" name="ends_at" value="{{ old('ends_at') }}" type="datetime-local" required></label>
                                <p class="text-xs leading-6 text-muted sm:col-span-2">Choose a time within opening hours and include setup and cleanup. Start and end on the same day. Availability is checked when you submit.</p>
                            </fieldset>
                            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-navy/10 pt-5">
                                <p class="max-w-lg text-sm leading-6 text-muted">We will email a verification link. The selected time is held for 24 hours only after you verify the request.</p>
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
