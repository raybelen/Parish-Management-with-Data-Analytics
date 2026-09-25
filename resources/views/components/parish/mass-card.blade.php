@props(['day', 'type', 'time', 'featured' => false])
<article @class(['flex min-h-56 flex-col gap-5 border px-7 py-8', 'border-navy bg-navy text-ivory' => $featured, 'border-navy/15' => ! $featured])>
    <p class="text-xs font-medium tracking-[0.18em]">{{ $day }}</p>
    <p @class(['text-sm', 'text-ivory/70' => $featured, 'text-muted' => ! $featured])>{{ $type }}</p>
    <p class="mt-auto text-2xl font-light tracking-tight sm:text-[1.65rem]">{{ $time }}</p><div class="h-px w-9 bg-gold"></div>
</article>
