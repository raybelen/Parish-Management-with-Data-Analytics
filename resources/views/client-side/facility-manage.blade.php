@extends('client-side.layouts.parish')

@section('content')
    @php($inputClass = 'w-full border border-navy/20 bg-white px-4 py-3 text-sm text-navy outline-none transition-colors placeholder:text-muted/55 focus:border-gold focus:ring-1 focus:ring-gold')
    <section class="border-b border-navy/10 bg-[#eae5da] py-14 sm:py-20">
        <div class="section-wrap max-w-4xl">
            <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-3 text-sm text-muted"><a href="{{ route('home') }}" class="inline-flex min-h-11 items-center hover:text-navy">Home</a><span aria-hidden="true">/</span><a href="{{ route('facilities.index') }}" class="inline-flex min-h-11 items-center hover:text-navy">Facilities</a><span aria-hidden="true">/</span><span aria-current="page">Manage</span></nav>
            @if(session('facility_access_notice'))<div class="mt-7 border-l-4 border-amber-700 bg-amber-50 px-6 py-5 text-sm text-amber-950" role="status">{{ session('facility_access_notice') }}</div>@endif
            @if(session('status'))<div class="mt-7 border-l-4 border-emerald-700 bg-emerald-50 px-6 py-5 text-sm text-emerald-950" role="status">{{ session('status') }}</div>@endif

            <div class="mt-8 grid gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16">
                <div><p class="section-label text-muted">Existing request</p><h1 class="mt-5 text-4xl leading-tight sm:text-5xl">Check your facility request</h1><p class="mt-5 text-base leading-8 text-muted">Enter your facility reference number and the contact email used when the request was submitted.</p><p class="mt-6 border-l-2 border-gold pl-5 text-sm leading-7 text-muted">References look like <span class="font-medium text-navy">FAC-AB12CD34EF56GH78</span>.</p></div>
                <form method="POST" action="{{ route('facilities.lookup.store') }}" class="grid gap-6 border border-navy/15 bg-ivory p-6 sm:p-9">
                    @csrf
                    @if($errors->any() && !$errors->getBag('recovery')->any())<div class="border-l-2 border-red-700 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert"><p class="font-medium">We could not find that facility request.</p><p class="mt-1">Check the reference and email, then try again.</p></div>@endif
                    <x-parish.form-field id="reference" name="reference" label="Facility reference number" :required="true"><input id="reference" name="reference" value="{{ old('reference') }}" required autocomplete="off" placeholder="FAC-AB12CD34EF56GH78" class="{{ $inputClass }} uppercase"></x-parish.form-field>
                    <x-parish.form-field id="contact_email" name="contact_email" label="Contact email" :required="true" help="Use the email saved on the original request."><input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email') }}" required autocomplete="email" placeholder="you@example.com" class="{{ $inputClass }}"></x-parish.form-field>
                    <button class="mt-2 inline-flex min-h-12 items-center justify-center gap-4 bg-gold px-6 py-3 text-sm font-medium text-navy transition-colors hover:bg-[#c9aa63]">Find my request <x-parish.icon name="arrow" class="size-4" /></button>
                    <a href="#forgot-reference" class="mx-auto inline-flex min-h-11 items-center text-sm font-medium text-navy underline decoration-gold underline-offset-4 hover:text-muted">Forgot your reference number?</a>
                </form>
            </div>

            <div id="forgot-reference" class="mt-10 scroll-mt-28 border border-navy/15 bg-navy p-6 text-ivory sm:p-9">
                <div class="grid gap-8 lg:grid-cols-[0.8fr_1.2fr] lg:gap-12">
                    <div><p class="section-label text-ivory/60">Reference recovery</p><h2 class="mt-4 text-3xl leading-tight sm:text-4xl">Forgot your reference?</h2><p class="mt-4 text-sm leading-7 text-ivory/70">Enter the organizer or group name and exact email used on the request. Matching reference numbers will be shown below.</p><p class="mt-5 border-l-2 border-gold pl-4 text-xs leading-6 text-ivory/60">For your privacy, both details must match. Recovery attempts are limited.</p></div>
                    <form method="POST" action="{{ route('facilities.bookings.recover') }}" class="grid gap-6 bg-ivory p-6 text-navy sm:p-8">
                        @csrf
                        @if($errors->getBag('recovery')->any())<div class="border-l-2 border-red-700 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert"><p class="font-medium">We could not recover a reference number.</p><p class="mt-1">Check the organizer name and contact email, then try again.</p></div>@endif
                        <x-parish.form-field id="organizer" name="organizer" label="Organizer or group name" :required="true" error-bag="recovery"><input id="organizer" name="organizer" value="{{ old('organizer') }}" required autocomplete="organization" class="{{ $inputClass }}"></x-parish.form-field>
                        <x-parish.form-field id="recovery_contact_email" name="contact_email" label="Contact email" :required="true" error-bag="recovery"><input id="recovery_contact_email" name="contact_email" type="email" value="{{ old('contact_email') }}" required autocomplete="email" class="{{ $inputClass }}"></x-parish.form-field>
                        <button class="inline-flex min-h-12 items-center justify-center gap-4 bg-gold px-6 py-3 text-sm font-medium text-navy transition-colors hover:bg-[#c9aa63]">Recover my reference number <x-parish.icon name="arrow" class="size-4" /></button>
                    </form>
                </div>

                @isset($recoveredFacilityBookings)
                    <section aria-labelledby="recovered-facilities-heading" class="mt-8 border-t border-ivory/15 pt-8">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div><p class="section-label text-ivory/60">Matching requests</p><h2 id="recovered-facilities-heading" class="mt-3 text-3xl">Your facility references</h2></div>
                            <p class="text-xs leading-5 text-ivory/60">Showing up to 10 recent requests. Detail links expire after 30 minutes.</p>
                        </div>
                        <div class="mt-6 grid gap-4 md:grid-cols-2">
                            @foreach($recoveredFacilityBookings as $recoveredBooking)
                                <article class="flex flex-col gap-5 border border-ivory/15 bg-ivory p-5 text-navy sm:p-6">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div><p class="text-xs font-medium tracking-[0.14em] uppercase text-muted">{{ $recoveredBooking['facility'] }}</p><p class="mt-2 break-all font-mono text-lg font-semibold tracking-wide">{{ $recoveredBooking['reference'] }}</p><div class="mt-3"><x-parish.copy-reference :value="$recoveredBooking['reference']" /></div></div>
                                        <span class="bg-gold/20 px-3 py-1 text-xs font-semibold text-[#705824]">{{ $recoveredBooking['status'] }}</span>
                                    </div>
                                    <p class="border-t border-navy/10 pt-3 text-sm text-muted">{{ $recoveredBooking['schedule'] }}</p>
                                    <a href="{{ $recoveredBooking['url'] }}" class="mt-auto inline-flex min-h-11 items-center justify-between border-t border-navy/15 pt-4 text-sm font-medium text-navy hover:text-muted">View request details <x-parish.icon name="arrow" class="size-4" /></a>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endisset
            </div>
        </div>
    </section>
@endsection
