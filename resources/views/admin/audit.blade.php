@extends('admin.layout')
@section('title', 'Audit trail')
@section('content')
    <div class="grid gap-2">
        <p class="text-sm leading-6 text-muted">A protected, readable history of important office activity. Entries cannot be edited or deleted through the application.</p>
        <p class="text-xs text-muted">Showing {{ number_format($logs->total()) }} {{ \Illuminate\Support\Str::plural('entry', $logs->total()) }}.</p>
    </div>

    <form class="admin-card grid gap-4 md:grid-cols-2 xl:grid-cols-[minmax(14rem,1.4fr)_minmax(12rem,1fr)_minmax(12rem,1fr)_auto] xl:items-end" method="GET">
        <x-admin.field label="Search activity" name="q" :value="request('q')" placeholder="Action, person, or note" />
        <x-admin.field label="Activity type" name="category">
            <select class="admin-input" name="category">
                <option value="">All activity types</option>
                @foreach($categories as $value => $label)
                    <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-admin.field>
        <x-admin.field label="Performed by" name="actor">
            <select class="admin-input" name="actor">
                <option value="">Everyone</option>
                @foreach($actors as $actor)
                    <option value="{{ $actor->id }}" @selected((string) request('actor') === (string) $actor->id)>{{ $actor->name }}</option>
                @endforeach
            </select>
        </x-admin.field>
        <div class="flex flex-wrap gap-2">
            <button class="admin-secondary">Apply filters</button>
            @if(request()->hasAny(['q', 'category', 'actor']))
                <a class="admin-secondary" href="{{ route('admin.audit.index') }}">Clear</a>
            @endif
        </div>
    </form>

    <div class="grid gap-3">
        @forelse($logs as $activity)
            <details class="admin-card group overflow-hidden p-0">
                <summary class="grid cursor-pointer list-none gap-3 p-4 marker:hidden sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:p-5">
                    <span @class([
                        'w-fit rounded-full px-2.5 py-1 text-[0.68rem] font-semibold tracking-wide uppercase',
                        'bg-blue-50 text-blue-800' => $activity['category'] === 'appointments',
                        'bg-emerald-50 text-emerald-800' => $activity['category'] === 'payments',
                        'bg-amber-50 text-amber-900' => $activity['category'] === 'donations',
                        'bg-cyan-50 text-cyan-900' => $activity['category'] === 'facilities',
                        'bg-violet-50 text-violet-800' => $activity['category'] === 'schedules',
                        'bg-orange-50 text-orange-900' => $activity['category'] === 'records',
                        'bg-slate-100 text-slate-700' => in_array($activity['category'], ['administration', 'other'], true),
                    ])>{{ $activity['category_label'] }}</span>
                    <span class="min-w-0">
                        <strong class="block text-sm leading-5 text-navy">{{ $activity['title'] }}</strong>
                        <span class="mt-1 block text-xs leading-5 text-muted">{{ $activity['actor'] }} · {{ $activity['occurred_at'] }} · {{ $activity['subject'] }}</span>
                    </span>
                    <span class="flex items-center gap-2 text-xs font-semibold text-muted">
                        View details
                        <x-parish.icon name="chevron-down" class="size-4 transition-transform group-open:rotate-180" />
                    </span>
                </summary>

                <div class="grid gap-5 border-t border-navy/10 bg-slate-50/70 p-4 sm:p-5">
                    @if($activity['reason'])
                        <div class="grid gap-1">
                            <h2 class="text-xs font-semibold tracking-wide text-muted uppercase">Reason or note</h2>
                            <p class="text-sm leading-6 text-navy">{{ $activity['reason'] }}</p>
                        </div>
                    @endif

                    @if($activity['changes'])
                        <div class="grid gap-3">
                            <h2 class="text-xs font-semibold tracking-wide text-muted uppercase">Recorded changes</h2>
                            <dl class="grid gap-2 lg:grid-cols-2">
                                @foreach($activity['changes'] as $change)
                                    <div class="rounded-xl border border-navy/10 bg-white p-3">
                                        <dt class="text-sm font-semibold text-navy">{{ $change['label'] }}</dt>
                                        <dd class="mt-1 text-xs leading-5 text-muted">
                                            @if($change['type'] === 'changed')
                                                <span class="block">Previous: <span class="text-navy">{{ $change['before'] }}</span></span>
                                                <span class="block">Now: <span class="font-medium text-navy">{{ $change['after'] }}</span></span>
                                            @elseif($change['type'] === 'added')
                                                <span>Set to <span class="font-medium text-navy">{{ $change['after'] }}</span></span>
                                            @elseif($change['type'] === 'removed')
                                                <span>Removed; previously <span class="text-navy">{{ $change['before'] }}</span></span>
                                            @else
                                                <span>Updated</span>
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @elseif(! $activity['reason'])
                        <p class="text-sm text-muted">No additional details were recorded for this activity.</p>
                    @endif
                </div>
            </details>
        @empty
            <div class="admin-card grid justify-items-start gap-2 text-sm">
                <p class="font-semibold text-navy">No matching activity found.</p>
                <p class="text-muted">Try clearing a filter or using a broader search.</p>
                <a class="text-link" href="{{ route('admin.audit.index') }}">Show all activity</a>
            </div>
        @endforelse
    </div>

    {{ $logs->links() }}
@endsection
