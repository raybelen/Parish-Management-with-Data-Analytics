@php
    $liturgicalMinistries = [
        ['name' => 'Ministry of Readers', 'description' => 'The Ministry of Readers is responsible for proclaiming the Sacred Scriptures during Holy Mass and other liturgical celebrations. Readers prepare carefully and practice their assigned readings so that they can proclaim God’s Word clearly, faithfully, and reverently. Through their service, they help the congregation reflect on Scripture and deepen their encounter with Christ.'],

        ['name' => 'Ministry of Altar Servers', 'description' => 'The Ministry of Altar Servers assists the priest and deacon during Mass and other liturgical celebrations. Their duties include preparing the altar and other liturgical items, carrying the cross and candles during processions, assisting at the altar, and helping maintain proper order and reverence throughout the celebration.'],

        ['name' => 'Music Ministry', 'description' => 'The Music Ministry supports the liturgy through music and singing. Its members lead the congregation in hymns and songs, prepare appropriate music for Mass and other celebrations, and practice together to ensure that the music contributes to prayer and worship. Their purpose is to help the congregation participate more fully and prayerfully in the liturgy.'],

        ['name' => 'Parish Youth Ministry', 'description' => 'The Parish Youth Ministry focuses on helping young people grow in their faith and become active members of the Church. It organizes gatherings, retreats, faith-formation activities, outreach programs, and other events for young people. It also encourages youth to develop leadership skills, serve others, and strengthen their relationship with Christ.'],

        ['name' => 'Ministry of Catechists', 'description' => 'The Ministry of Catechists is responsible for teaching and strengthening the faith of parishioners. Catechists provide instruction about Catholic teachings, Scripture, prayer, and Christian living. They may also prepare children, young people, and adults for the sacraments and help families understand and practice their faith in daily life.'],

        ['name' => 'Extraordinary Ministers of Holy Communion', 'description' => 'The Extraordinary Ministers of Holy Communion assist the priest and deacon in distributing Holy Communion when their assistance is needed. They serve the Eucharist with reverence and care during Mass and, when properly commissioned and assigned, may also bring Holy Communion to sick or homebound parishioners. Their service helps ensure that the faithful can receive the Eucharist in a dignified and orderly manner.'],
    ];

    $organizations = [
        ['name' => 'Knights of Columbus', 'description' => 'The Knights of Columbus is a Catholic organization that promotes faith, charity, fraternity, and service. Its members participate in charitable activities, support parish programs, assist people and families in need, and contribute to various Church and community projects. Through these activities, they put Catholic values of service and brotherhood into practice.'],

        ['name' => 'Mother Butler Guild', 'description' => 'The Mother Butler Guild is a parish service organization that traditionally supports the Church and its liturgical activities. Its members may help care for church furnishings, linens, and other items used during worship. They also assist with preparing the church for celebrations and participate in charitable and parish activities.'],

        ['name' => 'Neo-Catechumenal Way', 'description' => 'The Neo-Catechumenal Way is a Catholic community and formation program intended to help members deepen their Christian faith and live out their baptismal vocation. Members participate in Scripture-based formation, prayer, Eucharistic celebrations, and community activities. It also encourages evangelization and helps members grow in their commitment to Christian life and service.'],
    ];

    $ministrySections = [
        [
            'label' => 'Serve in Worship',
            'title' => 'Liturgical Ministries',
            'introduction' => 'Share your gifts in the prayer and sacramental life of our parish community.',
            'items' => $liturgicalMinistries,
        ],
        [
            'label' => 'Grow in Community',
            'title' => 'Organizations',
            'introduction' => 'Find fellowship, deepen your faith, and serve the parish alongside others.',
            'items' => $organizations,
        ],
    ];
@endphp

<div class="section-wrap py-16 sm:py-24">
    <section aria-labelledby="ministries-introduction-heading" class="grid gap-8 border-b border-navy/10 pb-12 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1fr)] lg:items-end lg:gap-20 lg:pb-16">
        <div>
            <p class="section-label mb-5 text-muted">Parish Life</p>
            <h2 id="ministries-introduction-heading" class="text-4xl leading-tight sm:text-5xl">A Place to Serve and Belong</h2>
        </div>
        <p class="max-w-2xl text-base leading-8 text-muted">Our parish community grows through people who pray, serve, learn, and walk together in faith. Explore the ministries and organizations where you can share your gifts and become more deeply involved in parish life.</p>
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
                <p class="section-label mb-4 text-ivory/65">Take the Next Step</p>
                <h2 id="ministries-contact-heading" class="text-3xl leading-tight text-ivory sm:text-4xl">Find Your Place in Parish Life</h2>
                <p class="mt-4 max-w-2xl text-base leading-8 text-ivory/70">Contact the parish office to learn more about joining a ministry or organization.</p>
            </div>
            <x-parish.button :href="route('contact')" class="w-full sm:w-auto">Contact the Parish Office</x-parish.button>
        </section>
    </div>
</div>
