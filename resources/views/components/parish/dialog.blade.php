@props(['id', 'title', 'wide' => false])
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-title" {{ $attributes->class($wide
    ? 'max-h-[90vh] w-[min(880px,94vw)] max-w-none overflow-hidden rounded-2xl border border-navy/10 bg-[#fbfaf7] p-0 text-navy shadow-2xl'
    : 'max-h-[85vh] w-[min(600px,92vw)] max-w-none overflow-y-auto border border-gold/45 bg-ivory p-7 text-navy sm:p-10') }}>
    <div class="{{ $wide ? 'flex items-start justify-between gap-5 border-b border-navy/10 bg-white px-5 py-4 sm:px-7 sm:py-5' : 'mb-6 flex items-start justify-between gap-5' }}">
        <h2 id="{{ $id }}-title" class="{{ $wide ? 'text-2xl leading-tight sm:text-3xl' : 'text-3xl leading-tight sm:text-4xl' }}">{{ $title }}</h2>
        <button type="button" data-close-dialog aria-label="Close dialog" class="{{ $wide ? 'flex size-10 shrink-0 items-center justify-center rounded-lg border border-navy/15 text-xl transition hover:border-navy/30 hover:bg-navy/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy' : 'flex size-11 shrink-0 items-center justify-center border border-navy/20 text-2xl' }}">×</button>
    </div>
    <div class="{{ $wide ? 'max-h-[calc(90vh-73px)] overflow-y-auto px-5 py-5 text-base leading-7 text-muted sm:px-7 sm:py-6' : 'text-base leading-8 text-muted' }}">{{ $slot }}</div>
</dialog>
