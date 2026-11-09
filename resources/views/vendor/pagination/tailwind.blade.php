@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm leading-5 text-muted">
            {{ __('Showing') }}
            <span class="font-semibold text-navy">{{ $paginator->firstItem() ?? 0 }}</span>
            {{ __('to') }}
            <span class="font-semibold text-navy">{{ $paginator->lastItem() ?? 0 }}</span>
            {{ __('of') }}
            <span class="font-semibold text-navy">{{ $paginator->total() }}</span>
            {{ __('results') }}
        </p>

        <div class="flex flex-wrap items-center gap-2">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="{{ __('Previous') }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-navy/10 bg-slate-100 px-3.5 text-sm font-medium text-muted/60">
                    <x-parish.icon name="arrow" class="size-4 rotate-180" />
                    <span class="hidden sm:inline">{{ __('Previous') }}</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="admin-secondary gap-2 !px-3.5" aria-label="{{ __('Previous') }}">
                    <x-parish.icon name="arrow" class="size-4 rotate-180" />
                    <span class="hidden sm:inline">{{ __('Previous') }}</span>
                </a>
            @endif

            <div role="group" class="flex items-center gap-1" aria-label="Page selection">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-disabled="true" class="grid size-11 place-items-center text-sm text-muted">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page === $paginator->currentPage())
                                <span aria-current="page" aria-label="{{ __('Page :page', ['page' => $page]) }}" class="grid size-11 place-items-center rounded-xl bg-navy text-sm font-semibold text-white shadow-sm">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}" class="grid size-11 place-items-center rounded-xl border border-navy/15 bg-white text-sm font-semibold text-navy shadow-xs transition hover:border-gold/50 hover:bg-ivory/70">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="admin-secondary gap-2 !px-3.5" aria-label="{{ __('Next') }}">
                    <span class="hidden sm:inline">{{ __('Next') }}</span>
                    <x-parish.icon name="arrow" class="size-4" />
                </a>
            @else
                <span aria-disabled="true" aria-label="{{ __('Next') }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-navy/10 bg-slate-100 px-3.5 text-sm font-medium text-muted/60">
                    <span class="hidden sm:inline">{{ __('Next') }}</span>
                    <x-parish.icon name="arrow" class="size-4" />
                </span>
            @endif
        </div>
    </nav>
@endif
