@props([
    'id',
    'name',
    'label',
    'required' => false,
    'optional' => false,
    'help' => null,
    'errorBag' => 'default',
])

<div {{ $attributes->merge(['class' => 'grid gap-2']) }}>
    <label for="{{ $id }}" class="flex flex-wrap items-center gap-2 text-sm font-medium text-navy">
        <span>{{ $label }}</span>
        @if ($required)
            <span class="text-[10px] font-semibold tracking-[0.12em] uppercase text-[#80662e]">Required</span>
        @elseif ($optional)
            <span class="text-[10px] font-medium tracking-[0.12em] uppercase text-muted">Optional</span>
        @endif
    </label>
    {{ $slot }}
    @if ($help)
        <p class="text-xs leading-5 text-muted">{{ $help }}</p>
    @endif
    @error($name, $errorBag)
        <p class="text-sm text-red-700" role="alert">{{ $message }}</p>
    @enderror
</div>
