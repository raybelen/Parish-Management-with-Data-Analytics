@if (request()->routeIs('home'))
    <section id="contact" class="bg-navy text-ivory">
        <div class="section-wrap grid gap-10 py-16 lg:grid-cols-[1fr_1.3fr] lg:gap-20">
            <x-parish.section-heading label="Office Hours" title="We're Here to Help" :light="true" />
            <div class="grid gap-8 sm:grid-cols-[1.4fr_1fr]">
                <div class="border-l border-gold/45 pl-6">
                    <p class="mb-5 text-sm text-[#d4ba80]">Tuesdays to Sundays</p>
                    <div class="flex flex-col gap-3 text-xl font-light">
                        <p>8:00 AM – 11:30 AM</p>
                        <p>2:00 PM – 5:00 PM</p>
                    </div>
                </div>
                <div class="border-l border-ivory/15 pl-6">
                    <p class="mb-5 text-sm text-[#d4ba80]">Mondays</p>
                    <p class="text-base text-ivory/70">No Office Hours</p>
                </div>
            </div>
        </div>
        <div id="appointment" class="section-wrap border-t border-ivory/15 py-20 text-center sm:py-24">
            <x-parish.icon name="cross" class="mx-auto mb-7 size-8 text-gold" />
            <h2 class="text-4xl sm:text-5xl lg:text-6xl">Need to Visit the Parish Office?</h2>
            <p class="mx-auto mt-6 max-w-2xl text-base leading-8 text-ivory/65">Whether you are inquiring about a sacrament, seeking parish assistance, requesting information, or simply need to speak with someone at the parish office, we're here to help.</p>
            <div class="mt-9 flex flex-col justify-center gap-4 sm:flex-row">
                <a href="{{ route('appointments.index') }}" class="inline-flex min-h-12 items-center justify-center gap-5 bg-gold px-7 py-3 text-sm font-medium text-navy transition-colors hover:bg-[#c9aa63]">Set an Appointment <x-parish.icon name="arrow" class="size-4" /></a>
                <x-parish.button :href="route('contact')" :secondary="true">Contact Us</x-parish.button>
            </div>
        </div>
    </section>
@else
    @php
        $contactMethods = [
            [
                'icon' => 'facebook',
                'title' => 'Facebook',
                'name' => 'St. John Nepomucene Parish',
                'detail' => 'Facebook page link to be added.',
            ],
            [
                'icon' => 'phone',
                'title' => 'Phone',
                'name' => 'Parish Office',
                'detail' => 'Phone number to be added.',
            ],
            [
                'icon' => 'mail',
                'title' => 'Email',
                'name' => 'Parish Office Email',
                'detail' => 'Email address to be added.',
            ],
        ];
    @endphp

    <section id="contact">
        <div class="section-wrap py-16 sm:py-24">
            <div class="grid gap-8 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:items-end lg:gap-20">
                <x-parish.section-heading label="Contact Us" title="Connect With Our Parish" />
                <p class="max-w-2xl text-base leading-8 text-muted">Reach the parish office through the contact method most convenient for you. We welcome questions about sacraments, documents, parish activities, and pastoral assistance.</p>
            </div>

            <div class="mt-12 grid gap-8 md:grid-cols-3 md:gap-6">
                @foreach ($contactMethods as $method)
                    <article class="group border-t border-navy/15 pt-7 transition-colors hover:border-gold">
                        <div class="flex size-11 items-center justify-center rounded-full border border-gold/45 text-[#80662e] transition-colors group-hover:bg-gold group-hover:text-navy">
                            <x-parish.icon :name="$method['icon']" class="size-5" />
                        </div>
                        <p class="section-label mt-7 text-muted">{{ $method['title'] }}</p>
                        <h2 class="mt-4 text-2xl leading-tight">{{ $method['name'] }}</h2>
                        <p class="mt-3 text-sm leading-7 text-muted">{{ $method['detail'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>

        <div class="border-y border-navy/10 bg-[#eae5da]">
            <div class="section-wrap grid gap-10 py-16 sm:py-20 lg:grid-cols-[1fr_1.3fr] lg:gap-20">
                <x-parish.section-heading label="Office Hours" title="We're Here to Help" />
                <div class="grid gap-8 sm:grid-cols-[1.4fr_1fr]">
                    <div class="border-l border-gold pl-6">
                        <p class="mb-5 text-sm font-medium text-[#80662e]">Tuesdays to Sundays</p>
                        <div class="flex flex-col gap-3 text-xl font-light">
                            <p>8:00 AM – 11:30 AM</p>
                            <p>2:00 PM – 5:00 PM</p>
                        </div>
                    </div>
                    <div class="border-l border-navy/15 pl-6">
                        <p class="mb-5 text-sm font-medium text-[#80662e]">Mondays</p>
                        <p class="text-base text-muted">No Office Hours</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="appointment" class="bg-navy text-ivory">
            <div class="section-wrap py-20 text-center sm:py-24">
                <x-parish.icon name="cross" class="mx-auto mb-7 size-8 text-gold" />
                <h2 class="text-4xl text-ivory sm:text-5xl lg:text-6xl">Need to Visit the Parish Office?</h2>
                <p class="mx-auto mt-6 max-w-2xl text-base leading-8 text-ivory/65">Whether you are inquiring about a sacrament, seeking parish assistance, requesting information, or simply need to speak with someone at the parish office, we're here to help.</p>
                <div class="mx-auto mt-9 max-w-2xl border border-gold/45 p-7 text-left sm:p-9">
                    <h3 class="text-3xl text-ivory">Arrange a Parish Visit</h3>
                    <p class="mt-4 text-base leading-8 text-ivory/75">Submit an online request for Baptism, Wedding, or Funeral services. You can also use your reference number to review an existing request.</p>
                    <x-parish.button :href="route('appointments.index')" class="mt-7 w-full sm:w-auto">Set an Appointment</x-parish.button>
                </div>
            </div>
        </div>
    </section>
@endif
