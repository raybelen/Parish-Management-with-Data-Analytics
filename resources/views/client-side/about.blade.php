@extends('client-side.layouts.parish')

@section('content')
    <div class="section-wrap border-b border-navy/10 pt-9 pb-9"><nav aria-label="Breadcrumb" class="flex items-center gap-3 text-sm text-muted"><a href="{{ route('home') }}" class="min-h-11 inline-flex items-center hover:text-navy">Home</a><span aria-hidden="true">/</span><span aria-current="page">{{ $pageTitle }}</span></nav><h1 class="mt-4 font-display text-4xl sm:text-5xl">{{ $pageTitle }}</h1></div>

    <div class="section-wrap space-y-20 py-16 sm:py-24">
        <section aria-labelledby="about-church-heading" class="grid items-center gap-10 lg:grid-cols-2 lg:gap-20">
            <div class="flex aspect-[4/3] items-center justify-center rounded-xl border border-gold/35 bg-[#eae5da] text-sm text-muted" role="img" aria-label="Church photo placeholder">
                Church Photo Placeholder
            </div>
            <div>
                <p class="section-label mb-5 text-muted">The Parish Church</p>
                <h2 id="about-church-heading" class="text-4xl leading-tight sm:text-5xl">About the Church</h2>
                <div class="mt-6 space-y-4 text-base leading-8 text-muted">
                    <p>St. John Nepomucene Parish is a Roman Catholic parish established in 1958. For many years, our church has been a place of worship, prayer, and fellowship, bringing together families and individuals in the celebration of the Holy Eucharist and the Sacraments.</p>
                    <p>As a parish community, we strive to grow together in faith and follow the teachings of Jesus Christ. Through our liturgical celebrations, parish activities, and service to others, we continue to build a welcoming and faithful community centered on Christ.</p>
                </div>
                <p class="mt-7 border-l border-gold pl-5 font-display text-2xl italic leading-8 text-navy/80">A sacred home for worship, prayer, and community.</p>
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
                <p class="section-label mb-5 text-muted">Our Story</p>
                <h2 id="history-heading" class="text-4xl leading-tight sm:text-5xl">History of the Parish</h2>
                <div class="mt-6 space-y-4 text-base leading-8 text-muted">
                    <p>Founded in 1958, St. John Nepomucene Parish has been part of the spiritual journey of generations of Catholic faithful. Through the years, the parish has grown alongside the community, providing a place where people can gather in prayer, celebrate their faith, and share important moments in their lives.</p>
                    <p>Today, we continue to honor that heritage by keeping our faith alive and passing it on to future generations.</p>
                </div>
                <p class="mt-7 border-l border-gold pl-5 font-display text-2xl italic leading-8 text-navy/80">A living heritage of faith since 1958.</p>
            </div>
        </section>

        <section aria-labelledby="patron-saint-heading" class="grid items-center gap-10 lg:grid-cols-2 lg:gap-20">
            <div class="flex aspect-[4/3] items-center justify-center rounded-xl border border-gold/35 bg-[#eae5da] text-sm text-muted" role="img" aria-label="Patron saint photo placeholder">
                Patron Saint Photo Placeholder
            </div>
            <div>
                <p class="section-label mb-5 text-muted">Our Patron</p>
                <h2 id="patron-saint-heading" class="text-4xl leading-tight sm:text-5xl">St. John Nepomucene</h2>
                <div class="mt-6 space-y-4 text-base leading-8 text-muted">
                    <p>Saint John Nepomucene is the patron saint of our parish. He was a Catholic priest and martyr known for his strong faith, courage, and devotion to the Church. His life is remembered as an example of remaining faithful to God even during difficult times.</p>
                    <p>As a parish dedicated to Saint John Nepomucene, we look to his life and example for inspiration. May his faithfulness encourage us to live our Catholic faith with courage, humility, and love, and to serve God and our fellow brothers and sisters.</p>
                </div>
                <p class="mt-7 border-l border-gold pl-5 font-display text-2xl italic leading-8 text-navy/80">Faithful in witness. Courageous in service.</p>
                <a href="{{ route('ministries') }}" class="mt-7 inline-flex min-h-12 items-center justify-center gap-4 border border-navy/25 px-6 py-3 text-sm font-medium text-navy transition-colors hover:border-gold hover:text-[#80662e]">Explore Ministries <x-parish.icon name="arrow" class="size-4" /></a>
            </div>
        </section>
    </div>

@endsection
