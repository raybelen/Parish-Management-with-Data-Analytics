@extends('client-side.layouts.parish')

@section('content')
    @php
        $majorServices = [
            [
                'number' => '01',
                'title' => 'Baptism',
                'description' => 'Welcome a child or adult into the Catholic faith and the life of the Church through the Sacrament of Baptism.',
                'image' => 'images/service-baptism.png',
                'image_alt' => 'A Catholic baptism at the baptismal font',
                'modal_id' => 'baptism-requirements',
            ],
            [
                'number' => '02',
                'title' => 'Wedding',
                'description' => 'Prepare to celebrate the Sacrament of Holy Matrimony with the guidance and support of our parish community.',
                'image' => 'images/service-wedding.png',
                'image_alt' => 'A couple holding hands during a Catholic wedding ceremony',
                'modal_id' => 'wedding-requirements',
            ],
            [
                'number' => '03',
                'title' => 'Funeral',
                'description' => 'Receive prayerful pastoral support as the parish accompanies your family in remembering and commending a loved one to God.',
                'image' => 'images/service-funeral.png',
                'image_alt' => 'A Catholic funeral liturgy inside a parish church',
                'modal_id' => 'funeral-requirements',
            ],
        ];

        $otherServices = [
            [
                'title' => 'First Holy Communion',
                'description' => 'Preparation for children who will receive Jesus in the Holy Eucharist for the first time.',
            ],
            [
                'title' => 'Confirmation',
                'description' => 'Faith formation for those preparing to receive the gifts of the Holy Spirit through Confirmation.',
            ],
            [
                'title' => 'House, Vehicle & Religious Item Blessings',
                'description' => 'Request a blessing for your home, vehicle, or religious items as an expression of faith and gratitude.',
            ],
            [
                'title' => 'Anointing of the Sick',
                'description' => 'Pastoral care and the Sacrament of Anointing for parishioners facing serious illness, frailty, or surgery.',
            ],
            [
                'title' => 'Confession',
                'description' => 'Encounter God’s mercy and reconciliation through the Sacrament of Penance.',
            ],
            [
                'title' => 'Celebratory Mass',
                'description' => 'Arrange a Mass for meaningful occasions such as anniversaries, birthdays, and family milestones.',
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
                <x-parish.section-heading id="major-services-heading" label="Sacraments & Pastoral Care" title="Services" />
                <p class="max-w-2xl text-base leading-8 text-muted">Our parish accompanies individuals and families through life’s most meaningful moments. Begin by reviewing the service you need, then contact the parish office for scheduling and guidance.</p>
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
                <x-parish.section-heading id="other-services-heading" label="More Ways We Serve" title="Other Services" />
                <p class="max-w-2xl text-base leading-8 text-muted">For preparation, availability, and service-specific requirements, please coordinate directly with the parish office.</p>
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
                    <p class="section-label mb-4 text-ivory/65">Parish Assistance</p>
                    <h2 class="text-3xl leading-tight text-ivory sm:text-4xl">Let Us Help You Prepare</h2>
                    <p class="mt-4 max-w-2xl text-base leading-8 text-ivory/70">Contact the parish office for sacrament inquiries, scheduling, document guidance, and special celebrations.</p>
                </div>
                <x-parish.button :href="route('contact')" class="w-full sm:w-auto">Contact the Parish Office</x-parish.button>
            </div>
        </div>
    </section>

    @push('dialogs')
        @foreach ($majorServices as $service)
            <x-parish.dialog :id="$service['modal_id']" :title="$service['title'].' Requirements'">
                <p>Complete requirements and preparation details will be added here.</p>
                <div class="mt-7 grid gap-6 border-t border-navy/10 pt-7 sm:grid-cols-2">
                    <section>
                        <h3 class="font-sans text-sm font-medium text-navy">Requirements</h3>
                        <p class="mt-2 text-sm leading-7">Requirement list placeholder.</p>
                    </section>
                    <section>
                        <h3 class="font-sans text-sm font-medium text-navy">Reminders</h3>
                        <p class="mt-2 text-sm leading-7">Scheduling and preparation reminders placeholder.</p>
                    </section>
                </div>
                <a href="{{ route('contact') }}" class="text-link mt-8">Contact the Parish Office <x-parish.icon name="arrow" class="size-4" /></a>
            </x-parish.dialog>
        @endforeach
    @endpush
@endsection
