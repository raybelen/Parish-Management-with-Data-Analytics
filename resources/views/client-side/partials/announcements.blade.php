        <section id="announcements" @class(['py-20 sm:py-28', 'section-wrap' => request()->routeIs('home')])>
            <div @class(['section-wrap' => ! request()->routeIs('home')])>
            <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                <x-parish.section-heading label="Parish Life" title="What's Happening in Our Parish" />
                <p class="max-w-xs text-sm leading-7 text-muted">The latest parish news, celebrations, and opportunities to take part.</p>
            </div>
            <div @class(['mt-10 grid gap-5', 'md:grid-cols-3' => request()->routeIs('home'), 'lg:grid-cols-2' => ! request()->routeIs('home')])>
                @foreach ($announcements as $announcement)
                    <article class="flex flex-col border border-navy/15 bg-ivory p-6 sm:p-8">
                        <time class="text-[11px] font-medium tracking-[0.14em] text-[#80662e] uppercase">{{ $announcement['date'] }}</time>
                        <h3 class="mt-3 text-3xl leading-tight text-navy">{{ $announcement['title'] }}</h3>
                        <p class="mt-4 text-sm leading-7 text-muted">{{ $announcement['copy'] }}</p>

                        @if (request()->routeIs('home'))
                            <a class="text-link mt-6 self-start" href="{{ route('announcements') }}">Learn More <x-parish.icon name="arrow" class="size-4" /></a>
                        @else
                            <details class="parish-accordion mt-6 border-t border-navy/15 pt-1">
                                <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-4 text-sm font-medium text-navy marker:hidden">
                                    <span>Learn More</span>
                                    <svg class="accordion-chevron size-4 shrink-0 text-[#80662e] transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m5 9 7 7 7-7" /></svg>
                                </summary>
                                <div class="space-y-5 border-t border-navy/10 pt-5 text-sm leading-7 text-muted">
                                    @foreach ($announcement['schedule'] as $schedule)
                                        <div>
                                            @if ($schedule['heading'])
                                                <p class="font-medium text-navy">{{ $schedule['heading'] }}</p>
                                            @endif
                                            <ul @class(['space-y-1', 'mt-2' => $schedule['heading']])>
                                                @foreach ($schedule['items'] as $item)
                                                    <li class="flex gap-3"><span class="text-gold" aria-hidden="true">•</span><span>{{ $item }}</span></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </article>
                @endforeach
            </div>

            @if (request()->routeIs('home'))
                <div class="mt-12 text-center"><a class="inline-flex min-h-12 items-center gap-5 border border-navy/25 px-7 text-sm transition-colors hover:border-gold" href="{{ route('announcements') }}">View All Announcements <x-parish.icon name="arrow" class="size-4" /></a></div>
            @endif
            </div>
        </section>
