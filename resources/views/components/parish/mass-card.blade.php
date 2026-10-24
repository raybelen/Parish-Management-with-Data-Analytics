@props(['type', 'entries', 'featured' => false])
<article @class(['flex min-h-56 flex-col gap-5 border px-7 py-8', 'border-navy bg-navy text-ivory' => $featured, 'border-navy/15' => ! $featured])>
    <h3 class="font-sans text-xs font-medium uppercase tracking-[0.18em]">{{ $type }}</h3>
    <ul class="flex flex-col gap-6">
        @foreach ($entries as $entry)
            <li>
                <p class="text-base font-medium leading-6">{{ $entry['church'] }}</p>
                <p @class(['mt-2 text-sm', 'text-ivory/70' => $featured, 'text-muted' => ! $featured])>{{ $entry['day'] }}</p>
                <p class="mt-1 text-2xl font-light tracking-tight sm:text-[1.65rem]">{{ $entry['time'] }}</p>
            </li>
        @endforeach
    </ul>
    <div class="mt-auto h-px w-9 bg-gold"></div>
</article>
