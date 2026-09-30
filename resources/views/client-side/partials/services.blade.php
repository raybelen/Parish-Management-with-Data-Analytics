        <section id="mass-schedule" @class([
            'py-20 sm:py-24',
            'bg-[#eae5da]' => request()->routeIs('home'),
            'bg-ivory' => ! request()->routeIs('home'),
        ])>
            <div class="section-wrap">
                <div class="flex flex-col justify-between gap-7 lg:flex-row lg:items-end">
                    <x-parish.section-heading label="Mass Schedule" title="Gather With Us in Worship">Come together with our parish community in the celebration of the Holy Mass.</x-parish.section-heading>
                    @if (request()->routeIs('home'))<a class="text-link self-start lg:shrink-0" href="{{ route('calendar') }}">View Calendar <x-parish.icon name="calendar" class="size-4" /></a>@endif
                </div>
                <div class="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    @foreach (config('parish.regular_schedules', []) as $schedule)
                        <x-parish.mass-card
                            :day="$schedule['day_label']"
                            :type="$schedule['service']"
                            :time="implode(' & ', array_column($schedule['times'], 'label'))"
                            :featured="$schedule['featured']"
                        />
                    @endforeach
                </div>
                <p class="mt-6 text-xs text-muted">There is always a place for you in our pews.</p>
            </div>
        </section>
