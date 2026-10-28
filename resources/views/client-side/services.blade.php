@extends('client-side.layouts.parish')

@section('content')
    @php
        $majorServices = [
            [
                'number' => '01',
                'title' => $websiteContent['service_baptism_title'],
                'description' => $websiteContent['service_baptism_description'],
                'image' => 'images/service-baptism.png',
                'image_alt' => 'A Catholic baptism at the baptismal font',
                'modal_id' => 'baptism-requirements',
                'service_type' => 'baptism',
            ],
            [
                'number' => '02',
                'title' => $websiteContent['service_wedding_title'],
                'description' => $websiteContent['service_wedding_description'],
                'image' => 'images/service-wedding.png',
                'image_alt' => 'A couple holding hands during a Catholic wedding ceremony',
                'modal_id' => 'wedding-requirements',
                'service_type' => 'wedding',
            ],
            [
                'number' => '03',
                'title' => $websiteContent['service_funeral_title'],
                'description' => $websiteContent['service_funeral_description'],
                'image' => 'images/service-funeral.png',
                'image_alt' => 'A Catholic funeral liturgy inside a parish church',
                'modal_id' => 'funeral-requirements',
                'service_type' => 'funeral',
            ],
        ];

        $otherServices = [
            [
                'title' => $websiteContent['service_communion_title'],
                'description' => $websiteContent['service_communion_description'],
            ],
            [
                'title' => $websiteContent['service_confirmation_title'],
                'description' => $websiteContent['service_confirmation_description'],
            ],
            [
                'title' => $websiteContent['service_blessings_title'],
                'description' => $websiteContent['service_blessings_description'],
            ],
            [
                'title' => $websiteContent['service_anointing_title'],
                'description' => $websiteContent['service_anointing_description'],
            ],
            [
                'title' => $websiteContent['service_confession_title'],
                'description' => $websiteContent['service_confession_description'],
            ],
            [
                'title' => $websiteContent['service_celebratory_title'],
                'description' => $websiteContent['service_celebratory_description'],
            ],
        ];
    @endphp

    <div class="section-wrap border-b border-navy/10 pt-9 pb-9">
        <nav aria-label="Breadcrumb" class="flex items-center gap-3 text-sm text-muted">
            <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center hover:text-navy">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $pageTitle }}</span>
        </nav>
        <h1 class="mt-4 font-display text-4xl sm:text-5xl">{{ $pageTitle }}</h1>
    </div>

    @include('client-side.partials.services')

    <section aria-labelledby="major-services-heading" class="bg-[#eae5da] py-16 sm:py-24">
        <div class="section-wrap">
            <div class="grid gap-8 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:items-end lg:gap-20">
                <x-parish.section-heading id="major-services-heading" :label="$websiteContent['services_major_label']" :title="$websiteContent['services_major_title']" />
                <p class="max-w-2xl text-base leading-8 text-muted">{{ $websiteContent['services_major_copy'] }}</p>
            </div>

            <div class="mt-12 grid gap-5 lg:grid-cols-3">
                @foreach ($majorServices as $service)
                    <article class="flex min-h-full flex-col overflow-hidden border border-navy/15 bg-white/60 transition-colors hover:border-gold/60">
                        <img src="{{ asset($service['image']) }}" alt="{{ $service['image_alt'] }}" width="1536" height="1152" class="aspect-4/3 w-full object-cover" loading="lazy">
                        <div class="flex flex-1 flex-col p-6 sm:p-8">
                            <span class="font-display text-lg text-[#80662e]" aria-hidden="true">{{ $service['number'] }}</span>
                            <h3 class="mt-5 text-3xl leading-tight">{{ $service['title'] }}</h3>
                            <p class="mt-4 flex-1 text-sm leading-7 text-muted">{{ $service['description'] }}</p>
                            <button type="button" data-dialog-target="{{ $service['modal_id'] }}" class="mt-8 flex min-h-12 w-full items-center justify-between gap-4 border-t border-navy/10 pt-4 text-left text-sm font-medium text-navy transition-colors hover:text-[#80662e]">
                                <span>View Requirements</span>
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full border border-gold/40" aria-hidden="true"><x-parish.icon name="arrow" class="size-4" /></span>
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section aria-labelledby="other-services-heading" class="border-y border-navy/10 bg-ivory py-16 sm:py-24">
        <div class="section-wrap">
            <div class="grid gap-8 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:items-end lg:gap-20">
                <x-parish.section-heading id="other-services-heading" :label="$websiteContent['services_other_label']" :title="$websiteContent['services_other_title']" />
                <p class="max-w-2xl text-base leading-8 text-muted">{{ $websiteContent['services_other_copy'] }}</p>
            </div>

            <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($otherServices as $service)
                    <article class="border border-navy/15 bg-white p-6 sm:p-8">
                        <p class="section-label mb-5 text-muted">Parish Service</p>
                        <h3 class="text-2xl leading-tight">{{ $service['title'] }}</h3>
                        <p class="mt-4 text-sm leading-7 text-muted">{{ $service['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#eae5da] py-16 sm:py-20">
        <div class="section-wrap">
            <div class="grid gap-8 bg-navy px-7 py-10 text-ivory sm:px-10 sm:py-12 lg:grid-cols-[1fr_auto] lg:items-center lg:gap-16 lg:px-14">
                <div>
                    <p class="section-label mb-4 text-ivory/65">{{ $websiteContent['services_assistance_label'] }}</p>
                    <h2 class="text-3xl leading-tight text-ivory sm:text-4xl">{{ $websiteContent['services_assistance_title'] }}</h2>
                    <p class="mt-4 max-w-2xl text-base leading-8 text-ivory/70">{{ $websiteContent['services_assistance_copy'] }}</p>
                </div>
                <x-parish.button :href="route('contact')" class="w-full sm:w-auto">Contact the Parish Office</x-parish.button>
            </div>
        </div>
    </section>

    @push('dialogs')
        @foreach ($majorServices as $service)
            <x-parish.dialog :id="$service['modal_id']" :title="$service['title'].' Requirements'">
                <p class="text-sm leading-7 text-muted">Please prepare these documents and bring the physical copies to the church office for verification.</p>
                <ul class="mt-7 grid gap-3 border-t border-navy/10 pt-7 sm:grid-cols-2">
                    @forelse ($appointmentServices[$service['service_type']]['documents'] ?? [] as $requirement)
                        <li class="flex gap-3 border border-navy/10 bg-ivory px-4 py-3 text-sm leading-6 text-navy">
                            <span aria-hidden="true" class="mt-2 size-1.5 shrink-0 rounded-full bg-gold"></span>
                            <span>{{ $requirement['label'] }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-muted">No document requirements are currently listed.</li>
                    @endforelse
                </ul>
                <a href="{{ route('contact') }}" class="text-link mt-8">Contact the Parish Office <x-parish.icon name="arrow" class="size-4" /></a>
            </x-parish.dialog>
        @endforeach
    @endpush
@endsection
