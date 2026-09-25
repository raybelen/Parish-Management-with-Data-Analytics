        <section id="gallery" @class(['py-20 sm:py-28', 'bg-[#eae5da]' => request()->routeIs('home')])>
            <div class="section-wrap">
            <div class="flex flex-col justify-between gap-7 sm:flex-row sm:items-end"><x-parish.section-heading label="Our Community" title="Moments of Faith" />@if (request()->routeIs('home'))<a class="text-link self-start" href="{{ route('gallery') }}">View Gallery <x-parish.icon name="arrow" class="size-4" /></a>@endif</div>
            <div class="mt-12 grid auto-rows-[260px] gap-4 sm:grid-cols-12 sm:auto-rows-[230px] lg:auto-rows-[270px]">
                @foreach ($photos as $photo)
                    <button data-gallery-index="{{ $loop->index }}" class="group relative overflow-hidden bg-navy {{ $photo['class'] }}" aria-label="Enlarge image: {{ $photo['title'] }}"><img src="{{ $photo['src'] }}" alt="{{ $photo['alt'] }}" loading="lazy" width="1200" height="1000" class="gallery-photo"><div class="absolute inset-x-0 bottom-0 flex items-center justify-between bg-navy/80 px-6 py-4 text-ivory"><span class="font-display text-xl">{{ $photo['title'] }}</span><span aria-hidden="true" class="text-gold">↗</span></div></button>
                @endforeach
            </div>
            <p class="mt-5 text-xs text-muted">Illustrative church photography. Parish community photographs will be shared here.</p>
            </div>
        </section>
