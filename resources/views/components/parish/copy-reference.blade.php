@props(['value', 'variant' => 'default'])

<button
    type="button"
    data-copy-reference="{{ $value }}"
    aria-label="Copy reference {{ $value }}"
    @class([
        'inline-flex min-h-10 items-center justify-center border px-3 py-2 text-xs font-medium transition-colors disabled:cursor-wait disabled:opacity-70 focus-visible:outline-2 focus-visible:outline-offset-2',
        'border-navy/20 text-navy hover:border-gold hover:bg-white focus-visible:outline-navy' => $variant === 'default',
        'border-ivory/30 text-ivory hover:border-gold hover:text-gold focus-visible:outline-gold' => $variant === 'inverse',
    ])
>
    <span data-copy-reference-label aria-live="polite">Copy reference</span>
</button>
