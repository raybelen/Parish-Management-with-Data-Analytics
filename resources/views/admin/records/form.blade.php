@extends('admin.layout')
@section('title', $record->exists ? $record->record_number : 'Add historical record')
@section('actions')<a class="admin-secondary" href="{{ route('admin.records.index') }}">Browse records</a><a class="admin-secondary" href="{{ route('admin.records.all') }}">Search all</a>@endsection
@section('content')
    <p class="text-sm text-muted">{{ !$record->exists || $record->source === 'archival' ? 'Historical register entry preserved separately from current appointment operations.' : 'Preserved service record. Corrections are audited and the linked appointment remains unchanged.' }}</p>
    <form class="admin-card grid gap-4 sm:grid-cols-2" method="POST" action="{{ $record->exists ? route('admin.records.update', $record) : route('admin.records.store') }}">@csrf @if($record->exists)@method('PUT')@endif<input type="hidden" name="version" value="{{ $record->version }}">
        <x-admin.field label="Record number" name="record_number" :value="$record->record_number" required />
        <x-admin.field label="Service" name="service_type"><select class="admin-input" name="service_type" required>@foreach($services as $service)<option value="{{ $service->slug }}" @selected(old('service_type', $record->service_type) === $service->slug)>{{ $service->name }}</option>@endforeach</select></x-admin.field>
        <x-admin.field label="Service date" name="service_date" type="date" :value="$record->service_date?->toDateString()" required /><x-admin.field label="Participant names" name="participant_names" :value="$record->participant_names" required />
        <x-admin.field label="Church / chapel" name="church" :value="$record->church" required /><x-admin.field label="Officiating priest" name="officiant" :value="$record->officiant" />
        @foreach(['register_book' => 'Register book', 'register_page' => 'Page', 'register_entry' => 'Entry number'] as $field => $label)<x-admin.field :label="$label" :name="$field" :value="$record->$field" />@endforeach
        <x-admin.field label="Notes" name="notes" :value="$record->notes" />
        <label class="grid gap-2 text-sm sm:col-span-2"><span>Additional service details / register transcription</span><textarea class="admin-input" name="details_text" rows="5">{{ old('details_text', $record->details['transcription'] ?? '') }}</textarea></label>
        @can('records.manage')<x-admin.field label="Reason for entry / correction" name="reason" required /><button class="admin-button self-end">{{ $record->exists ? 'Save correction with history' : 'Save historical record' }}</button>@endcan
    </form>
    @if($record->exists)
        @if($record->details)<details class="admin-card"><summary class="cursor-pointer font-semibold">Preserved service details</summary><dl class="mt-4 grid gap-3 text-sm">@foreach($record->details as $field => $value)@if(is_scalar($value))<div><dt class="text-muted">{{ \Illuminate\Support\Str::headline($field) }}</dt><dd class="whitespace-pre-line">{{ $value }}</dd></div>@endif
@endforeach</dl></details>@endif
        @can('records.manage')<form class="admin-card grid items-end gap-4 sm:grid-cols-2" method="POST" action="{{ route('admin.records.destroy', $record) }}" data-confirm="Change this record's archive status? Its content and history will remain preserved.">@csrf @method('DELETE')<input type="hidden" name="version" value="{{ $record->version }}"><input type="hidden" name="archive" value="{{ $record->archived_at ? 0 : 1 }}"><x-admin.field label="Archive / restore reason" name="reason" required /><button class="admin-secondary">{{ $record->archived_at ? 'Restore to active records' : 'Archive record' }}</button></form>@endcan
        <section class="admin-card"><h2 class="mb-4 text-2xl">Record history</h2>@foreach($history as $entry)<details class="border-b border-navy/10 py-3 text-sm"><summary class="cursor-pointer">{{ \Illuminate\Support\Str::headline($entry->action) }} · {{ $entry->created_at->format('M j, Y g:i A') }} · {{ $entry->actor?->name ?? 'System' }}</summary><p class="my-3">{{ $entry->reason }}</p><pre class="max-h-80 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-50 p-4 text-xs">{{ json_encode(['before' => $entry->before, 'after' => $entry->after], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>@endforeach</section>
    @endif
@endsection
