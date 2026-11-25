<table class="admin-table w-full">
    <caption class="sr-only">Baptism register entries</caption>
    <thead><tr>
        @foreach(['Name', 'Baptism date', 'Parents', 'Godparents', 'Priest', 'Action'] as $heading)
            <th scope="col" class="sticky top-0 z-10">{{ $heading }}</th>
        @endforeach
    </tr></thead>
    <tbody>
        @foreach($records as $record)
            <tr>
                <td>
                    <a class="font-semibold underline decoration-gold underline-offset-4" href="{{ route('admin.records.show', $record) }}">{{ \App\Support\ServiceRegister::value($record, 'child_full_name') ?: $record->participant_names }}</a>
                    <p class="mt-1 text-xs text-muted">{{ $record->record_number }}</p>
                    @if($record->archived_at)<span class="text-xs text-muted">Archived</span>@endif
                </td>
                <td class="whitespace-nowrap">{{ $record->service_date?->format('M j, Y') ?? '—' }}</td>
                @foreach([['father_name' => 'Father', 'mother_name' => 'Mother'], ['godfather_name' => 'Godfather', 'godmother_name' => 'Godmother']] as $names)
                    <td><dl class="grid gap-1">
                        @foreach($names as $field => $label)
                            <div><dt class="inline text-xs text-muted">{{ $label }}:</dt> <dd class="inline break-words">{{ \App\Support\ServiceRegister::value($record, $field) ?: '—' }}</dd></div>
                        @endforeach
                    </dl></td>
                @endforeach
                <td>{{ $record->officiant ?: '—' }}</td>
                <td><a data-register-toggle aria-controls="register-details-{{ $record->id }}" aria-expanded="false" class="inline-flex min-h-11 items-center whitespace-nowrap text-sm font-semibold underline decoration-gold underline-offset-4" href="{{ route('admin.records.show', $record) }}">View details</a><div class="mt-2">@include('admin.records.partials.print-link', ['record' => $record])</div></td>
            </tr>
            <tr id="register-details-{{ $record->id }}" hidden>
                <td colspan="6" class="bg-slate-50">
                    <div class="grid gap-5 p-1 sm:grid-cols-2 lg:grid-cols-3">
                        @php
                            $detailGroups = [
                                'Birth information' => ['birth_year' => 'Birthday year', 'birth_month_day' => 'Birthday month / day', 'child_place_of_birth' => 'Birthplace'],
                                'Parents’ addresses' => ['father_origin_address' => 'Father’s origin address', 'mother_origin_address' => 'Mother’s origin address', 'parents_current_residence' => 'Current residence'],
                                'Godparents’ addresses' => ['godfather_address' => 'Godfather’s address', 'godmother_address' => 'Godmother’s address'],
                            ];
                        @endphp
                        @foreach($detailGroups as $heading => $fields)
                            <section>
                                <h3 class="font-sans text-sm font-semibold">{{ $heading }}</h3>
                                <dl class="mt-3 grid gap-3">
                                    @foreach($fields as $field => $label)
                                        <div><dt class="text-xs text-muted">{{ $label }}</dt><dd class="break-words">{{ \App\Support\ServiceRegister::value($record, $field) ?: '—' }}</dd></div>
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
