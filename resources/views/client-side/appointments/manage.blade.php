@extends('client-side.layouts.parish')

@section('content')
    @php
        $inputClass = 'w-full border border-navy/20 bg-white px-4 py-3 text-sm text-navy outline-none transition-colors placeholder:text-muted/55 focus:border-gold focus:ring-1 focus:ring-gold';
    @endphp

    <section class="border-b border-navy/10 bg-[#eae5da] py-14 sm:py-20">
        <div class="section-wrap max-w-4xl">
            <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-3 text-sm text-muted">
                <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center hover:text-navy">Home</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('appointments.index') }}" class="inline-flex min-h-11 items-center hover:text-navy">Appointments</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Manage</span>
            </nav>

            <div class="mt-8 grid gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16">
                <div>
                    <p class="section-label text-muted">Existing Request</p>
                    <h1 class="mt-5 text-4xl leading-tight sm:text-5xl">Find your appointment</h1>
                    <p class="mt-5 text-base leading-8 text-muted">Enter the reference number from your confirmation and either the email address or contact number used when you submitted the request.</p>
                    <p class="mt-6 border-l-2 border-gold pl-5 text-sm leading-7 text-muted">Reference numbers look like <span class="font-medium text-navy">APT-260927-ABC123</span>.</p>
                </div>

                <form method="POST" action="{{ route('appointments.lookup.store') }}" class="grid gap-6 border border-navy/15 bg-ivory p-6 sm:p-9">
                    @csrf

                    @if ($errors->any())
                        <div class="border-l-2 border-red-700 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert">
                            <p class="font-medium">We could not find that appointment.</p>
                            <p class="mt-1">Check the reference number and contact information, then try again.</p>
                        </div>
                    @endif

                    <x-parish.form-field id="reference_number" name="reference_number" label="Appointment / reference number" :required="true">
                        <input id="reference_number" name="reference_number" type="text" value="{{ old('reference_number') }}" required autocomplete="off" placeholder="APT-260927-ABC123" class="{{ $inputClass }} uppercase">
                    </x-parish.form-field>

                    <x-parish.form-field id="contact_information" name="contact_information" label="Email address or contact number" :required="true" help="Use the contact information saved on the original request.">
                        <input id="contact_information" name="contact_information" type="text" required autocomplete="email" placeholder="you@example.com or 0912 345 6789" class="{{ $inputClass }}">
                    </x-parish.form-field>

                    <button type="submit" class="mt-2 inline-flex min-h-12 items-center justify-center gap-4 bg-gold px-6 py-3 text-sm font-medium text-navy transition-colors hover:bg-[#c9aa63]">
                        Find my appointment <x-parish.icon name="arrow" class="size-4" />
                    </button>
                    <a href="#forgot-reference" class="mx-auto inline-flex min-h-11 items-center text-sm font-medium text-navy underline decoration-gold underline-offset-4 hover:text-muted">Forgot your reference number?</a>
                    <a href="{{ route('appointments.index') }}" class="mx-auto inline-flex min-h-11 items-center text-sm text-muted underline decoration-gold underline-offset-4 hover:text-navy">Back to appointment options</a>
                </form>
            </div>

            <div id="forgot-reference" class="mt-10 scroll-mt-28 border border-navy/15 bg-navy p-6 text-ivory sm:p-9">
                <div class="grid gap-8 lg:grid-cols-[0.8fr_1.2fr] lg:gap-12">
                    <div>
                        <p class="section-label text-ivory/60">Reference Recovery</p>
                        <h2 class="mt-4 text-3xl leading-tight sm:text-4xl">Forgot your reference number?</h2>
                        <p class="mt-4 text-sm leading-7 text-ivory/70">Enter the full name and exact email address or contact number used when the appointment was requested.</p>
                        <p class="mt-5 border-l-2 border-gold pl-4 text-xs leading-6 text-ivory/60">For your privacy, both details must match. Recovery attempts are limited.</p>
                    </div>

                    <form method="POST" action="{{ route('appointments.reference-recovery.store') }}" class="grid gap-6 bg-ivory p-6 text-navy sm:p-8">
                        @csrf

                        @if ($errors->getBag('recovery')->any())
                            <div class="border-l-2 border-red-700 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert">
                                <p class="font-medium">We could not recover a reference number.</p>
                                <p class="mt-1">Check the name and contact information, then try again.</p>
                            </div>
                        @endif

                        <x-parish.form-field id="recovery_full_name" name="recovery_full_name" label="Full name used on the request" :required="true" error-bag="recovery">
                            <input id="recovery_full_name" name="recovery_full_name" type="text" value="{{ old('recovery_full_name') }}" required autocomplete="name" autocapitalize="words" data-title-case class="{{ $inputClass }}">
                        </x-parish.form-field>

                        <x-parish.form-field id="recovery_contact_information" name="recovery_contact_information" label="Email address or contact number" :required="true" error-bag="recovery" help="Use the contact information saved on the original request.">
                            <input id="recovery_contact_information" name="recovery_contact_information" type="text" value="{{ old('recovery_contact_information') }}" required autocomplete="email" placeholder="you@example.com or 0912 345 6789" class="{{ $inputClass }}">
                        </x-parish.form-field>

                        <button type="submit" class="inline-flex min-h-12 items-center justify-center gap-4 bg-gold px-6 py-3 text-sm font-medium text-navy transition-colors hover:bg-[#c9aa63]">
                            Recover my reference number <x-parish.icon name="arrow" class="size-4" />
                        </button>
                    </form>
                </div>

                @isset($recoveredAppointments)
                    <section aria-labelledby="recovered-appointments-heading" class="mt-8 border-t border-ivory/15 pt-8">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p class="section-label text-ivory/60">Matching Requests</p>
                                <h2 id="recovered-appointments-heading" class="mt-3 text-3xl">Your appointment references</h2>
                            </div>
                            <p class="text-xs leading-5 text-ivory/60">Showing up to 10 recent requests. Links expire after 30 minutes.</p>
                        </div>

                        <div class="mt-6 grid gap-4 md:grid-cols-2">
                            @foreach ($recoveredAppointments as $recoveredAppointment)
                                <article class="flex flex-col gap-5 border border-ivory/15 bg-ivory p-5 text-navy sm:p-6">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-medium tracking-[0.14em] uppercase text-muted">{{ $recoveredAppointment['service'] }}</p>
                                            <p class="mt-2 font-mono text-lg font-semibold tracking-wide">{{ $recoveredAppointment['reference_number'] }}</p>
                                        </div>
                                        <span class="bg-gold/20 px-3 py-1 text-xs font-semibold text-[#705824]">{{ $recoveredAppointment['status'] }}</span>
                                    </div>
                                    <dl class="grid gap-2 text-sm">
                                        <div class="flex justify-between gap-4 border-t border-navy/10 pt-3">
                                            <dt class="text-muted">Preferred date</dt>
                                            <dd class="text-right font-medium">{{ $recoveredAppointment['preferred_date'] }}</dd>
                                        </div>
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-muted">Church / chapel</dt>
                                            <dd class="text-right font-medium">{{ $recoveredAppointment['preferred_church'] }}</dd>
                                        </div>
                                    </dl>
                                    <a href="{{ $recoveredAppointment['url'] }}" class="mt-auto inline-flex min-h-11 items-center justify-between border-t border-navy/15 pt-4 text-sm font-medium text-navy hover:text-muted">
                                        View appointment details <x-parish.icon name="arrow" class="size-4" />
                                    </a>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endisset
            </div>
        </div>
    </section>
@endsection
