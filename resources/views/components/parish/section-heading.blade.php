@props(['label', 'title', 'light' => false])
<div {{ $attributes }}>
    <p @class(['section-label mb-5', 'text-ivory/75' => $light, 'text-muted' => ! $light])>{{ $label }}</p>
    <h2 @class(['text-4xl leading-[1.12] sm:text-5xl lg:text-[3.5rem]', 'text-ivory' => $light])>{{ $title }}</h2>
    @if ($slot->isNotEmpty()) <div @class(['mt-6 max-w-xl text-base leading-8', 'text-ivory/70' => $light, 'text-muted' => ! $light])>{{ $slot }}</div> @endif
</div>
