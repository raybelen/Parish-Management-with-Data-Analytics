        <section id="about" class="section-wrap grid items-center gap-12 py-20 sm:py-28 lg:grid-cols-2 lg:gap-24">
            <div class="relative pb-7 pr-7">
                <div class="absolute inset-0 top-7 left-7 border border-gold/60"></div>
                <img src="{{ $interiorImage }}" alt="Light falling through a church interior, a peaceful setting for worship" width="1200" height="1400" loading="lazy" class="relative aspect-[4/4.4] w-full object-cover">
                <div class="absolute right-0 bottom-0 bg-ivory px-6 py-4 font-display text-xl italic">Rooted in faith. Open to all.</div>
            </div>
            <div>
                <x-parish.section-heading label="Welcome" title="A Community United in Faith">St. John Nepomucene Parish is a community gathered in faith and prayer. Together, we celebrate the Eucharist, grow in our relationship with Christ, and live out our calling through service, fellowship, and love for one another.</x-parish.section-heading>
                <p class="mt-7 border-t border-navy/15 pt-6 font-display text-2xl italic text-navy/80">A place to worship, belong, serve, and grow.</p>
                <a class="text-link mt-8" href="{{ route(request()->routeIs('home') ? 'about' : 'services') }}">{{ request()->routeIs('home') ? 'Discover Our Parish' : 'Join Us in Worship' }} <x-parish.icon name="arrow" class="size-4" /></a>
            </div>
        </section>
