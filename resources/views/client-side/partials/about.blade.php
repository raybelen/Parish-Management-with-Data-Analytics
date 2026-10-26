        <section id="about" class="section-wrap grid items-center gap-12 py-20 sm:py-28 lg:grid-cols-2 lg:gap-24">
            <div class="relative pb-7 pr-7">
                <div class="absolute inset-0 top-7 left-7 border border-gold/60"></div>
                <img src="{{ asset('images/home-welcome-church-interior.jpg') }}" alt="Parishioners gathered inside the church for worship" width="1280" height="960" loading="lazy" decoding="async" class="relative aspect-[4/3] w-full object-cover">
                <div class="absolute right-0 bottom-0 bg-ivory px-6 py-4 font-display text-xl italic">{{ $websiteContent['home_about_image_caption'] }}</div>
            </div>
            <div>
                <x-parish.section-heading :label="$websiteContent['home_about_label']" :title="$websiteContent['home_about_title']">{{ $websiteContent['home_about_copy'] }}</x-parish.section-heading>
                <p class="mt-7 border-t border-navy/15 pt-6 font-display text-2xl italic text-navy/80">{{ $websiteContent['home_about_quote'] }}</p>
                <a class="text-link mt-8" href="{{ route(request()->routeIs('home') ? 'about' : 'services') }}">{{ request()->routeIs('home') ? 'Discover Our Parish' : 'Join Us in Worship' }} <x-parish.icon name="arrow" class="size-4" /></a>
            </div>
        </section>
