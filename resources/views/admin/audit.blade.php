@extends('admin.layout')
@section('title', 'Audit trail')
@section('content')
    <p class="text-sm text-muted">Protected history of administrative, financial, document and security events. Entries cannot be edited or deleted through the application.</p>
    <form class="admin-card flex flex-wrap items-end gap-4" method="GET"><x-admin.field label="Action contains" name="q" :value="request('q')" /><x-admin.field label="User ID" name="actor" type="number" :value="request('actor')" /><button class="admin-secondary">Filter history</button></form>
    <div class="grid gap-3">@forelse($logs as $entry)<details class="admin-card"><summary class="cursor-pointer text-sm"><strong>{{ \Illuminate\Support\Str::headline($entry->action) }}</strong> · {{ $entry->created_at->format('M j, Y g:i A') }} · {{ $entry->actor?->name ?? 'Client / system' }}</summary><p class="my-3 text-sm">{{ class_basename($entry->entity_type ?? 'System') }} #{{ $entry->entity_id }} · {{ $entry->reason }}</p><pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-50 p-4 text-xs">{{ json_encode(['before' => $entry->before, 'after' => $entry->after], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>@empty<p class="admin-card text-sm text-muted">No matching audit entries.</p>@endforelse</div>{{ $logs->links() }}
@endsection
