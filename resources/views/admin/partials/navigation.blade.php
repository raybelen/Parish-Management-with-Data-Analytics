<div class="grid gap-6">
    @foreach ($navigationSections as $section => $items)
        @php($visibleItems = collect($items)->filter(fn (array $item): bool => $item['ability'] === null || auth()->user()->can($item['ability'])))
        @if ($visibleItems->isNotEmpty())
            <div class="grid gap-1">
                <p class="px-3 pb-1 text-[0.65rem] font-semibold tracking-[0.18em] text-muted uppercase">{{ $section }}</p>
                @foreach ($visibleItems as $item)
                    @php($isActive = request()->routeIs(...$item['active']))
                    <a href="{{ route($item['route']) }}" @class([
                        'group flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors',
                        'bg-navy text-white shadow-sm' => $isActive,
                        'text-navy hover:bg-ivory/80' => ! $isActive,
                    ]) @if($isActive) aria-current="page" @endif>
                        <x-parish.icon :name="$item['icon']" @class(['size-5 shrink-0', 'text-gold' => $isActive, 'text-muted transition-colors group-hover:text-navy' => ! $isActive]) />
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    @endforeach
</div>
