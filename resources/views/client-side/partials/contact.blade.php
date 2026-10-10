@if (request()->routeIs('home'))
    <section id="contact" class="bg-navy text-ivory">
        <div class="section-wrap grid gap-10 py-16 lg:grid-cols-[1fr_1.3fr] lg:gap-20">
            <x-parish.section-heading :label="$websiteContent['office_hours_label']" :title="$websiteContent['office_hours_title']" :light="true" />
            <div class="grid gap-8 sm:grid-cols-[1.4fr_1fr]">
                <div class="border-l border-gold/45 pl-6">
                    <p class="mb-5 text-sm text-[#d4ba80]">{{ $websiteContent['office_open_days'] }}</p>
                    <div class="flex flex-col gap-3 text-xl font-light">
                        <p>{{ $websiteContent['office_morning_hours'] }}</p>
                        <p>{{ $websiteContent['office_afternoon_hours'] }}</p>
                    </div>
                </div>
                <div class="border-l border-ivory/15 pl-6">
                    <p class="mb-5 text-sm text-[#d4ba80]">{{ $websiteContent['office_closed_day'] }}</p>
                    <p class="text-base text-ivory/70">{{ $websiteContent['office_closed_message'] }}</p>
                </div>
            </div>
        </div>
        <div id="appointment" class="section-wrap border-t border-ivory/15 py-20 text-center sm:py-24">
            <x-parish.icon name="cross" class="mx-auto mb-7 size-8 text-gold" />
            <h2 class="text-4xl sm:text-5xl lg:text-6xl">{{ $websiteContent['contact_cta_title'] }}</h2>
            <p class="mx-auto mt-6 max-w-2xl text-base leading-8 text-ivory/65">{{ $websiteContent['contact_cta_copy'] }}</p>
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
                'title' => $websiteContent['contact_facebook_title'],
                'name' => $websiteContent['contact_facebook_name'],
                'detail' => $websiteContent['contact_facebook_detail'],
            ],
            [
                'icon' => 'phone',
                'title' => $websiteContent['contact_phone_title'],
                'name' => $websiteContent['contact_phone_name'],
                'detail' => $websiteContent['contact_phone_detail'],
            ],
            [
                'icon' => 'mail',
                'title' => $websiteContent['contact_email_title'],
                'name' => $websiteContent['contact_email_name'],
                'detail' => $websiteContent['contact_email_detail'],
            ],
        ];
    @endphp

    <section id="contact">
        <div class="section-wrap py-16 sm:py-24">
            <div class="grid gap-8 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:items-end lg:gap-20">
                <x-parish.section-heading :label="$websiteContent['contact_label']" :title="$websiteContent['contact_title']" />
                <p class="max-w-2xl text-base leading-8 text-muted">{{ $websiteContent['contact_copy'] }}</p>
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
                <x-parish.section-heading :label="$websiteContent['office_hours_label']" :title="$websiteContent['office_hours_title']" />
                <div class="grid gap-8 sm:grid-cols-[1.4fr_1fr]">
                    <div class="border-l border-gold pl-6">
                        <p class="mb-5 text-sm font-medium text-[#80662e]">{{ $websiteContent['office_open_days'] }}</p>
                        <div class="flex flex-col gap-3 text-xl font-light">
                            <p>{{ $websiteContent['office_morning_hours'] }}</p>
                            <p>{{ $websiteContent['office_afternoon_hours'] }}</p>
                        </div>
                    </div>
                    <div class="border-l border-navy/15 pl-6">
                        <p class="mb-5 text-sm font-medium text-[#80662e]">{{ $websiteContent['office_closed_day'] }}</p>
                        <p class="text-base text-muted">{{ $websiteContent['office_closed_message'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="appointment" class="bg-navy text-ivory">
            <div class="section-wrap py-20 text-center sm:py-24">
                <x-parish.icon name="cross" class="mx-auto mb-7 size-8 text-gold" />
                <h2 class="text-4xl text-ivory sm:text-5xl lg:text-6xl">{{ $websiteContent['contact_cta_title'] }}</h2>
                <p class="mx-auto mt-6 max-w-2xl text-base leading-8 text-ivory/65">{{ $websiteContent['contact_cta_copy'] }}</p>
                <div class="mx-auto mt-9 max-w-2xl border border-gold/45 p-7 text-left sm:p-9">
                    <h3 class="text-3xl text-ivory">{{ $websiteContent['contact_visit_title'] }}</h3>
                    <p class="mt-4 text-base leading-8 text-ivory/75">{{ $websiteContent['contact_visit_copy'] }}</p>
                    <x-parish.button :href="route('appointments.index')" class="mt-7 w-full sm:w-auto">Set an Appointment</x-parish.button>
                </div>
            </div>
        </div>
    </section>
@endif
