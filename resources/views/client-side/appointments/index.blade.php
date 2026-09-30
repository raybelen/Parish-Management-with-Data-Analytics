@extends('client-side.layouts.parish')

@section('content')
    <section class="border-b border-navy/10 bg-ivory py-14 sm:py-20">
        <div class="section-wrap max-w-5xl">
            <nav aria-label="Breadcrumb" class="flex items-center gap-3 text-sm text-muted">
                <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center hover:text-navy">Home</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Appointments</span>
            </nav>

            <div class="mt-8 max-w-3xl">
                <p class="section-label text-muted">Parish Appointments</p>
                <h1 class="mt-5 text-4xl leading-tight sm:text-6xl">What would you like to do?</h1>
                <p class="mt-5 text-base leading-8 text-muted">Start a request for Baptism, Wedding, or Funeral services, or review a request you have already submitted.</p>
            </div>

            <div class="mt-10 grid gap-5 md:grid-cols-2">
                <a href="{{ route('appointments.create') }}" class="group flex min-h-64 flex-col justify-between border border-navy/15 bg-white p-7 transition-colors hover:border-gold sm:p-9">
                    <span class="flex size-14 items-center justify-center rounded-full bg-gold/15 text-[#80662e]" aria-hidden="true">
                        <x-parish.icon name="calendar" class="size-7" />
                    </span>
                    <span class="mt-10">
                        <span class="block font-display text-3xl leading-tight">Make a new appointment</span>
                        <span class="mt-3 block text-sm leading-7 text-muted">Choose a service, share the required details, and upload supporting documents.</span>
                        <span class="mt-6 inline-flex items-center gap-3 text-sm font-medium text-[#80662e]">Start a request <x-parish.icon name="arrow" class="size-4 transition-transform group-hover:translate-x-1" /></span>
                    </span>
                </a>

                <a href="{{ route('appointments.lookup.create') }}" class="group flex min-h-64 flex-col justify-between border border-navy/15 bg-navy p-7 text-ivory transition-colors hover:border-gold sm:p-9">
                    <span class="flex size-14 items-center justify-center rounded-full border border-gold/45 text-gold" aria-hidden="true">
                        <x-parish.icon name="book" class="size-7" />
                    </span>
                    <span class="mt-10">
                        <span class="block font-display text-3xl leading-tight">Manage my existing appointment</span>
                        <span class="mt-3 block text-sm leading-7 text-ivory/65">Use your reference number and the email address or phone number on your request.</span>
                        <span class="mt-6 inline-flex items-center gap-3 text-sm font-medium text-gold">Find my appointment <x-parish.icon name="arrow" class="size-4 transition-transform group-hover:translate-x-1" /></span>
                    </span>
                </a>
            </div>
        </div>
    </section>
@endsection
