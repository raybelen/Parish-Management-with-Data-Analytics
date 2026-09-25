        <section id="mass-schedule" @class([
            'py-20 sm:py-24',
            'bg-[#eae5da]' => request()->routeIs('home'),
            'bg-ivory' => ! request()->routeIs('home'),
        ])>
            <div class="section-wrap">
                <div class="flex flex-col justify-between gap-7 lg:flex-row lg:items-end">
                    <x-parish.section-heading label="Mass Schedule" title="Gather With Us in Worship">Come together with our parish community in the celebration of the Holy Mass.</x-parish.section-heading>
                    @if (request()->routeIs('home'))<a class="text-link self-start lg:shrink-0" href="{{ route('services') }}">View Calendar</a>@endif
                </div>
                <div class="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <x-parish.mass-card day="WEEKDAYS" type="Daily Mass" time="7:00 AM" />
                    <x-parish.mass-card day="WEDNESDAY" type="Perpetual Mass" time="4:00 PM" />
                    <x-parish.mass-card day="SATURDAY" type="Anticipated Mass" time="4:00 PM" />
                    <x-parish.mass-card day="SUNDAY" type="Sunday Mass" time="7:00 AM & 4:00 PM" :featured="true" />
                </div>
                <p class="mt-6 text-xs text-muted">There is always a place for you in our pews.</p>
            </div>
        </section>
