@props(['label', 'name', 'value' => '', 'type' => 'text', 'required' => false, 'help' => null])
<label class="grid gap-2 text-sm">
    <span class="flex flex-wrap items-center gap-2 font-medium">
        <span>{{ $label }}</span>
        @if($required)<span class="text-[0.62rem] font-semibold tracking-[0.1em] text-[#80662e] uppercase">Required</span>@endif
    </span>
    @if($slot->isNotEmpty())
        {{ $slot }}
    @else
        <input name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required) {{ $attributes->class('admin-input') }}>
    @endif
    @if($help)<span class="text-xs leading-5 text-muted">{{ $help }}</span>@endif
    @error($name)<span class="text-xs font-medium text-red-700" role="alert">{{ $message }}</span>@enderror
</label>
