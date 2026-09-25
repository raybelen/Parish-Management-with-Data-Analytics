@props(['id', 'title'])
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-title" {{ $attributes->merge(['class' => 'max-h-[85vh] w-[min(600px,92vw)] max-w-none overflow-y-auto border border-gold/45 bg-ivory p-7 text-navy sm:p-10']) }}>
    <div class="mb-6 flex items-start justify-between gap-5"><h2 id="{{ $id }}-title" class="text-3xl leading-tight sm:text-4xl">{{ $title }}</h2><button data-close-dialog aria-label="Close dialog" class="flex size-11 shrink-0 items-center justify-center border border-navy/20 text-2xl">×</button></div>
    <div class="text-base leading-8 text-muted">{{ $slot }}</div>
</dialog>
