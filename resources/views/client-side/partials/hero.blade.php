        <section id="home" class="relative isolate flex min-h-[680px] items-center overflow-hidden bg-navy text-ivory lg:min-h-[760px]">
            <img src="{{ asset('images/church-hero.png') }}" alt="Our parish church and bell tower in warm afternoon light" fetchpriority="high" width="1536" height="1024" class="absolute inset-0 -z-20 h-full w-full object-cover object-center">
            <div class="hero-shade absolute inset-0 -z-10"></div>
            <div class="section-wrap hero-enter py-24 lg:py-32">
                <p class="section-label text-[10px] text-ivory/85 sm:text-xs">{{ $websiteContent['home_hero_label'] }}</p>
                <h1 class="mt-8 max-w-3xl text-[3.8rem] leading-[0.98] tracking-tight sm:text-7xl lg:text-[6.5rem]">{{ $websiteContent['home_hero_heading_primary'] }}<br><em class="font-normal text-[#d4ba80]">{{ $websiteContent['home_hero_heading_emphasis'] }}</em></h1>
                <p class="mt-8 font-display text-2xl sm:text-[1.7rem]">{{ $websiteContent['home_hero_subtitle'] }}</p>
                <p class="mt-5 max-w-[480px] text-sm leading-7 text-ivory/75 sm:text-base">{{ $websiteContent['home_hero_copy'] }}</p>
                <div class="mt-9 flex flex-col gap-4 sm:flex-row"><x-parish.button :href="route('services')">Join Us in Worship</x-parish.button><x-parish.button :secondary="true">Set an Appointment</x-parish.button></div>
                <a href="{{ route('about') }}" class="mt-16 inline-flex min-h-11 items-center gap-3 text-[10px] tracking-[0.22em] uppercase text-ivory/60"><span class="h-9 w-px bg-gold"></span>A place to belong <span aria-hidden="true">→</span></a>
            </div>
            <span class="absolute right-10 bottom-10 hidden text-[10px] tracking-[0.2em] text-ivory/70 uppercase lg:block">Faith · Prayer · Community · Service</span>
        </section>
