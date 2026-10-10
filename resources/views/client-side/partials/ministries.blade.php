@php
    $liturgicalMinistries = [
        ['name' => $websiteContent['ministry_readers_name'], 'description' => $websiteContent['ministry_readers_description']],

        ['name' => $websiteContent['ministry_servers_name'], 'description' => $websiteContent['ministry_servers_description']],

        ['name' => $websiteContent['ministry_music_name'], 'description' => $websiteContent['ministry_music_description']],

        ['name' => $websiteContent['ministry_youth_name'], 'description' => $websiteContent['ministry_youth_description']],

        ['name' => $websiteContent['ministry_catechists_name'], 'description' => $websiteContent['ministry_catechists_description']],

        ['name' => $websiteContent['ministry_communion_name'], 'description' => $websiteContent['ministry_communion_description']],
    ];

    $organizations = [
        ['name' => $websiteContent['organization_knights_name'], 'description' => $websiteContent['organization_knights_description']],

        ['name' => $websiteContent['organization_mother_butler_name'], 'description' => $websiteContent['organization_mother_butler_description']],

        ['name' => $websiteContent['organization_neocatechumenal_name'], 'description' => $websiteContent['organization_neocatechumenal_description']],
    ];

    $ministrySections = [
        [
            'label' => $websiteContent['ministries_liturgical_label'],
            'title' => $websiteContent['ministries_liturgical_title'],
            'introduction' => $websiteContent['ministries_liturgical_copy'],
            'items' => $liturgicalMinistries,
        ],
        [
            'label' => $websiteContent['ministries_organizations_label'],
            'title' => $websiteContent['ministries_organizations_title'],
            'introduction' => $websiteContent['ministries_organizations_copy'],
            'items' => $organizations,
        ],
    ];
@endphp

<div class="section-wrap py-16 sm:py-24">
    <section aria-labelledby="ministries-introduction-heading" class="grid gap-8 border-b border-navy/10 pb-12 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1fr)] lg:items-end lg:gap-20 lg:pb-16">
        <div>
            <p class="section-label mb-5 text-muted">{{ $websiteContent['ministries_intro_label'] }}</p>
            <h2 id="ministries-introduction-heading" class="text-4xl leading-tight sm:text-5xl">{{ $websiteContent['ministries_intro_title'] }}</h2>
        </div>
        <p class="max-w-2xl text-base leading-8 text-muted">{{ $websiteContent['ministries_intro_copy'] }}</p>
    </section>

    <div class="space-y-20 pt-20 sm:space-y-24 sm:pt-24">
        @foreach ($ministrySections as $section)
            <section aria-labelledby="ministry-section-{{ $loop->iteration }}">
                <div class="grid gap-6 lg:grid-cols-[minmax(0,0.72fr)_minmax(0,1.28fr)] lg:gap-16">
                    <div>
                        <p class="section-label mb-5 text-muted">{{ $section['label'] }}</p>
                        <h2 id="ministry-section-{{ $loop->iteration }}" class="text-4xl leading-tight sm:text-5xl">{{ $section['title'] }}</h2>
                        <p class="mt-5 max-w-md text-base leading-8 text-muted">{{ $section['introduction'] }}</p>
                    </div>

                    <div class="space-y-3">
                        @foreach ($section['items'] as $item)
                            <details class="parish-accordion group border border-navy/15 bg-white/55 transition-colors hover:border-gold/60 open:border-gold/60 open:bg-white/75">
                                <summary class="flex min-h-16 cursor-pointer list-none items-center gap-4 px-5 py-4 marker:hidden sm:gap-5 sm:px-6">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full border border-gold/40 font-display text-sm text-[#80662e]" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="flex-1 text-base font-medium leading-6 text-navy">{{ $item['name'] }}</span>
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full border border-navy/15 transition-colors group-hover:border-gold/60" aria-hidden="true">
                                        <svg class="accordion-chevron size-4 text-[#80662e] transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m5 9 7 7 7-7" /></svg>
                                    </span>
                                </summary>
                                <div class="grid gap-7 border-t border-navy/10 px-5 py-7 sm:px-6 md:grid-cols-[minmax(180px,240px)_1fr] md:items-start">
                                    <div class="flex aspect-4/3 items-center justify-center rounded-xl border border-gold/30 bg-[#eae5da] text-sm text-muted" role="img" aria-label="Photo placeholder for {{ $item['name'] }}">Photo Placeholder</div>
                                    <p class="max-w-2xl text-base leading-8 text-muted">{{ $item['description'] }}</p>
                                </div>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>
        @endforeach

        <section aria-labelledby="ministries-contact-heading" class="grid gap-8 bg-navy px-7 py-10 text-ivory sm:px-10 sm:py-12 lg:grid-cols-[1fr_auto] lg:items-center lg:gap-16 lg:px-14">
            <div>
                <p class="section-label mb-4 text-ivory/65">{{ $websiteContent['ministries_cta_label'] }}</p>
                <h2 id="ministries-contact-heading" class="text-3xl leading-tight text-ivory sm:text-4xl">{{ $websiteContent['ministries_cta_title'] }}</h2>
                <p class="mt-4 max-w-2xl text-base leading-8 text-ivory/70">{{ $websiteContent['ministries_cta_copy'] }}</p>
            </div>
            <x-parish.button :href="route('contact')" class="w-full sm:w-auto">Contact the Parish Office</x-parish.button>
        </section>
    </div>
</div>
