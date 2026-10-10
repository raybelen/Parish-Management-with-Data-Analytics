@extends('client-side.layouts.parish')

@section('content')
    <div class="section-wrap border-b border-navy/10 pt-9 pb-9"><nav aria-label="Breadcrumb" class="flex items-center gap-3 text-sm text-muted"><a href="{{ route('home') }}" class="min-h-11 inline-flex items-center hover:text-navy">Home</a><span aria-hidden="true">/</span><span aria-current="page">{{ $pageTitle }}</span></nav><h1 class="mt-4 font-display text-4xl sm:text-5xl">{{ $pageTitle }}</h1></div>

    <div class="section-wrap space-y-20 py-16 sm:py-24">
        <section aria-labelledby="about-church-heading" class="grid items-center gap-10 lg:grid-cols-2 lg:gap-20">
            <div class="flex aspect-[4/3] items-center justify-center rounded-xl border border-gold/35 bg-[#eae5da] text-sm text-muted" role="img" aria-label="Church photo placeholder">
                Church Photo Placeholder
            </div>
            <div>
                <p class="section-label mb-5 text-muted">{{ $websiteContent['about_church_label'] }}</p>
                <h2 id="about-church-heading" class="text-4xl leading-tight sm:text-5xl">{{ $websiteContent['about_church_title'] }}</h2>
                <div class="mt-6 space-y-4 text-base leading-8 text-muted">
                    <p>{{ $websiteContent['about_church_paragraph_one'] }}</p>
                    <p>{{ $websiteContent['about_church_paragraph_two'] }}</p>
                </div>
                <p class="mt-7 border-l border-gold pl-5 font-display text-2xl italic leading-8 text-navy/80">{{ $websiteContent['about_church_quote'] }}</p>
                <a href="{{ route('services') }}" class="mt-7 inline-flex min-h-12 items-center justify-center gap-4 border border-navy/25 px-6 py-3 text-sm font-medium text-navy transition-colors hover:border-gold hover:text-[#80662e]">View Mass Schedule <x-parish.icon name="arrow" class="size-4" /></a>
            </div>
        </section>

        <section aria-labelledby="history-heading" class="grid items-center gap-10 border-y border-navy/10 py-16 lg:grid-cols-2 lg:gap-20">
            <div class="lg:order-2">
                <div class="flex aspect-[4/3] items-center justify-center rounded-xl border border-gold/35 bg-[#eae5da] text-sm text-muted" role="img" aria-label="Parish history photo placeholder">
                    History Photo Placeholder
                </div>
            </div>
            <div class="lg:order-1">
                <p class="section-label mb-5 text-muted">{{ $websiteContent['about_history_label'] }}</p>
                <h2 id="history-heading" class="text-4xl leading-tight sm:text-5xl">{{ $websiteContent['about_history_title'] }}</h2>
                <div class="mt-6 space-y-4 text-base leading-8 text-muted">
                    <p>{{ $websiteContent['about_history_paragraph_one'] }}</p>
                    <p>{{ $websiteContent['about_history_paragraph_two'] }}</p>
                </div>
                <p class="mt-7 border-l border-gold pl-5 font-display text-2xl italic leading-8 text-navy/80">{{ $websiteContent['about_history_quote'] }}</p>
            </div>
        </section>

        <section aria-labelledby="patron-saint-heading" class="grid items-center gap-10 lg:grid-cols-2 lg:gap-20">
            <div class="flex aspect-[4/3] items-center justify-center rounded-xl border border-gold/35 bg-[#eae5da] text-sm text-muted" role="img" aria-label="Patron saint photo placeholder">
                Patron Saint Photo Placeholder
            </div>
            <div>
                <p class="section-label mb-5 text-muted">{{ $websiteContent['about_patron_label'] }}</p>
                <h2 id="patron-saint-heading" class="text-4xl leading-tight sm:text-5xl">{{ $websiteContent['about_patron_title'] }}</h2>
                <div class="mt-6 space-y-4 text-base leading-8 text-muted">
                    <p>{{ $websiteContent['about_patron_paragraph_one'] }}</p>
                    <p>{{ $websiteContent['about_patron_paragraph_two'] }}</p>
                </div>
                <p class="mt-7 border-l border-gold pl-5 font-display text-2xl italic leading-8 text-navy/80">{{ $websiteContent['about_patron_quote'] }}</p>
                <a href="{{ route('ministries') }}" class="mt-7 inline-flex min-h-12 items-center justify-center gap-4 border border-navy/25 px-6 py-3 text-sm font-medium text-navy transition-colors hover:border-gold hover:text-[#80662e]">Explore Ministries <x-parish.icon name="arrow" class="size-4" /></a>
            </div>
        </section>
    </div>

@endsection
