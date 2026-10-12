<div class="grid gap-4">
    @foreach ($navigationSections as $section => $items)
        @php($visibleItems = collect($items)->filter(fn (array $item): bool => $item['ability'] === null || auth()->user()->can($item['ability'])))
        @if ($visibleItems->isNotEmpty())
            <div>
                <p class="flex min-h-11 items-center px-3 text-xs font-semibold text-muted">
                    {{ $section }}
                </p>
                <div class="mt-1 grid gap-1">
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
            </div>
        @endif
    @endforeach
</div>
