@php
    $summaryColumns = match ($serviceType->slug) {
        'wedding' => ['Marriage date' => ['service_date' => null], 'Couple' => ['groom_full_name' => 'Groom', 'bride_full_name' => 'Bride'], 'Godparents' => ['godfather_name' => 'Godfather', 'godmother_name' => 'Godmother']],
        'funeral' => ['Date of death' => ['date_of_death' => null], 'Date of burial' => ['date_of_burial' => null], 'Informant' => ['contact_full_name' => null, 'contact_relationship' => 'Relationship']],
        default => ['Confirmation date' => ['service_date' => null], 'Parents' => ['father_name' => 'Father', 'mother_name' => 'Mother'], 'Godparents' => ['godfather_name' => 'Godfather', 'godmother_name' => 'Godmother']],
    };
    $detailGroups = [];
    foreach (\App\Support\ServiceRegister::fields($serviceType->slug) as $field => $definition) {
        $detailGroups[$definition['group'] ?? 'Service details'][$field] = $definition['label'];
    }
    $dateFields = array_filter($columns, fn ($field) => str_ends_with($field, '_year') || str_ends_with($field, '_month_day'), ARRAY_FILTER_USE_KEY);
    if ($dateFields) {
        $detailGroups['Register dates'] = $dateFields;
    }
@endphp
<table class="admin-table w-full">
    <caption class="sr-only">{{ $serviceType->name }} register entries</caption>
    <thead><tr>
        <th scope="col" class="sticky top-0 z-10">{{ $serviceType->slug === 'wedding' ? 'Record' : 'Name' }}</th>
        @foreach($summaryColumns as $heading => $fields)<th scope="col" class="sticky top-0 z-10">{{ $heading }}</th>@endforeach
        <th scope="col" class="sticky top-0 z-10">Priest</th>
        <th scope="col" class="sticky top-0 z-10">Action</th>
    </tr></thead>
    <tbody>
        @foreach($records as $record)
            <tr>
                <td>
                    @php($nameField = $serviceType->slug === 'funeral' ? 'deceased_full_name' : 'recipient_full_name')
                    <a class="font-semibold underline decoration-gold underline-offset-4" href="{{ route('admin.records.show', $record) }}">{{ $serviceType->slug === 'wedding' ? $record->record_number : (\App\Support\ServiceRegister::value($record, $nameField) ?: $record->participant_names) }}</a>
                    @if($serviceType->slug !== 'wedding')<p class="mt-1 text-xs text-muted">{{ $record->record_number }}</p>@endif
                    @if($record->archived_at)<p class="text-xs text-muted">Archived</p>@endif
                </td>
                @foreach($summaryColumns as $fields)
                    <td><dl class="grid gap-1">
                        @foreach($fields as $field => $label)
                            @php($value = $field === 'service_date' ? ($record->service_date?->format('M j, Y') ?? '') : \App\Support\ServiceRegister::value($record, $field))
                            <div>@if($label)<dt class="inline text-xs text-muted">{{ $label }}:</dt> @endif<dd class="inline break-words">{{ $value !== '' ? $value : '—' }}</dd></div>
                        @endforeach
                    </dl></td>
                @endforeach
                <td>{{ $record->officiant ?: '—' }}</td>
                <td><a data-register-toggle aria-controls="register-details-{{ $record->id }}" aria-expanded="false" class="inline-flex min-h-11 items-center whitespace-nowrap text-sm font-semibold underline decoration-gold underline-offset-4" href="{{ route('admin.records.show', $record) }}">View details</a></td>
            </tr>
            <tr id="register-details-{{ $record->id }}" hidden>
                <td colspan="6" class="bg-slate-50">
                    <div class="grid gap-5 p-1 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($detailGroups as $heading => $fields)
                            <section>
                                <h3 class="font-sans text-sm font-semibold">{{ $heading }}</h3>
                                <dl class="mt-3 grid gap-3">
                                    @foreach($fields as $field => $label)
                                        @php($value = \App\Support\ServiceRegister::value($record, $field))
                                        <div><dt class="text-xs text-muted">{{ $label }}</dt><dd class="break-words">{{ $value !== '' ? $value : '—' }}</dd></div>
                                    @endforeach
                                </dl>
                            </section>
                        @endforeach
                    </div>
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-navy/10 pt-3">
                        <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                            @foreach(['register_book' => 'Book', 'register_page' => 'Page', 'register_entry' => 'Entry'] as $field => $label)
                                <div><dt class="inline text-muted">{{ $label }}:</dt> <dd class="inline">{{ $record->$field ?: '—' }}</dd></div>
                            @endforeach
                        </dl>
                        <a class="admin-secondary" href="{{ route('admin.records.show', $record) }}">Open full record</a>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
