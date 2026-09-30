@props(['href' => null, 'secondary' => false])
<a href="{{ $href ?? route('appointments.index') }}" {{ $attributes->class([
    'inline-flex min-h-12 items-center justify-center gap-4 px-6 py-3 text-sm font-medium transition-colors',
    'bg-gold text-navy hover:bg-[#c9aa63]' => ! $secondary,
    'border border-ivory/40 text-ivory hover:border-gold hover:text-gold' => $secondary,
]) }}>{{ $slot }} <x-parish.icon name="arrow" class="size-4" /></a>
