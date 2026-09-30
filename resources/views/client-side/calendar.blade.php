@extends('client-side.layouts.parish')

@section('content')
    <section class="border-b border-navy/10 bg-[#eae5da] py-14 sm:py-20">
        <div class="section-wrap">
            <nav aria-label="Breadcrumb" class="flex items-center gap-3 text-sm text-muted">
                <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center hover:text-navy">Home</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Calendar</span>
            </nav>
            <div class="mt-7 grid gap-7 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.55fr)] lg:items-end lg:gap-16">
                <div>
                    <p class="section-label mb-5 text-muted">Parish Schedule</p>
                    <h1 class="text-5xl leading-none sm:text-6xl">{{ $calendarRange }}</h1>
                </div>
                <p class="max-w-xl text-base leading-8 text-muted">Plan ahead with our regular Masses and confirmed parish activities for this month and the next.</p>
            </div>
        </div>
    </section>

    <section aria-labelledby="calendar-heading" class="bg-ivory py-12 sm:py-18">
        <div class="mx-auto w-full max-w-375 px-5 sm:px-9 xl:px-12">
            <div class="flex flex-col justify-between gap-5 border-b border-navy/10 pb-7 sm:flex-row sm:items-end">
                <div>
                    <h2 id="calendar-heading" class="text-3xl sm:text-4xl">Calendar</h2>
                    <p class="mt-3 text-sm leading-7 text-muted">Times shown are parish local time. Confirmed activities list only the service type and time.</p>
                </div>
                <div class="flex flex-wrap gap-x-6 gap-y-3 text-xs font-medium text-muted" aria-label="Calendar legend">
                    <span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full bg-gold" aria-hidden="true"></span>Regular schedule</span>
                    <span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full bg-navy" aria-hidden="true"></span>Confirmed activity</span>
                </div>
            </div>

            <p class="mt-6 text-xs text-muted md:hidden">Scroll each month horizontally to see every day.</p>

            <div class="mt-4 grid gap-8 md:mt-8 2xl:grid-cols-2">
                @foreach ($calendarMonths as $month)
                    <section aria-labelledby="month-{{ $month['key'] }}" class="overflow-hidden border border-navy/15 bg-white">
                        <div class="flex items-center justify-between gap-5 bg-navy px-5 py-4 text-ivory sm:px-6">
                            <h3 id="month-{{ $month['key'] }}" class="text-2xl text-ivory">{{ $month['label'] }}</h3>
                            <x-parish.icon name="calendar" class="size-5 text-gold" />
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[43rem] table-fixed border-collapse">
                                <caption class="sr-only">Parish services and activities for {{ $month['label'] }}</caption>
                                <thead>
                                    <tr class="bg-[#eae5da] text-[11px] font-semibold tracking-[0.12em] text-muted uppercase">
                                        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                                            <th scope="col" class="border-r border-b border-navy/10 px-2 py-3 text-center last:border-r-0">{{ $weekday }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($month['weeks'] as $week)
                                        <tr>
                                            @foreach ($week as $day)
                                                @if ($day === null)
                                                    <td class="border-r border-b border-navy/10 bg-[#f7f4ed] last:border-r-0" aria-hidden="true"><div class="min-h-36"></div></td>
                                                @else
                                                    <td data-date="{{ $day['date'] }}" @class(['border-r border-b border-navy/10 p-2 align-top last:border-r-0', 'bg-gold/8' => $day['is_today']])>
                                                        <div class="min-h-36">
                                                            <time datetime="{{ $day['date'] }}" @class(['flex size-7 items-center justify-center rounded-full text-sm font-semibold', 'bg-navy text-ivory' => $day['is_today'], 'text-navy' => ! $day['is_today']])>{{ $day['day'] }}</time>
                                                            @if ($day['events'] !== [])
                                                                <ul class="mt-2 grid gap-1.5">
                                                                    @foreach ($day['events'] as $event)
                                                                        <li @class(['border-l-2 px-2 py-1.5 text-[11px] leading-4', 'border-gold bg-gold/10 text-[#705824]' => $event['kind'] === 'regular', 'border-navy bg-navy/8 text-navy' => $event['kind'] === 'scheduled'])>
                                                                            <span class="block font-semibold">{{ $event['name'] }}</span>
                                                                            <span class="block">{{ $event['time'] }}</span>
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            @endif
                                                        </div>
                                                    </td>
                                                @endif
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="mt-10 grid gap-6 border border-navy/15 bg-[#eae5da] p-6 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-center lg:gap-12">
                <div>
                    <p class="section-label mb-4 text-muted">Schedule a Service</p>
                    <h2 class="text-3xl">Need to request another parish activity?</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-muted">Submit your preferred service, date, and time. It will appear here only after the parish confirms the schedule.</p>
                </div>
                <x-parish.button :href="route('appointments.index')" class="w-full sm:w-auto">Set an Appointment</x-parish.button>
            </div>
        </div>
    </section>
@endsection
