@extends('client-side.layouts.parish')

@section('content')
    <div class="section-wrap py-10 sm:py-16">
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-3 text-sm text-muted">
            <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center hover:underline">Home</a>
            <span aria-hidden="true">/</span><span aria-current="page">{{ $pageTitle }}</span>
        </nav>
        <h1 class="mt-4 max-w-4xl font-display text-4xl text-navy sm:text-5xl">{{ $pageTitle }}</h1>
        <p class="mt-4 text-sm text-muted">Last updated: November 27, 2026</p>
        <div class="mt-10 grid gap-10 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <aside>
                <h2 class="font-sans text-sm font-semibold text-navy">Parish policies</h2>
                <nav aria-label="Related policies" class="mt-4 flex flex-col gap-2 text-sm text-muted">
                    @include('client-side.partials.policy-links')
                </nav>
            </aside>
            <article class="flex max-w-3xl flex-col gap-8 text-base leading-8 text-navy/85 [&_h2]:mb-3 [&_h2]:font-display [&_h2]:text-2xl [&_h2]:text-navy [&_a]:underline [&_a]:underline-offset-4">
                @yield('policy-content')
                <section>
                    <h2>Questions or concerns</h2>
                    <p>Contact St. John Nepomucene Parish through the <a href="{{ route('contact') }}">parish office contact page</a>. Include your appointment or receipt reference when relevant, but never send passwords, payment PINs, or one-time codes.</p>
                </section>
            </article>
        </div>
    </div>
@endsection
