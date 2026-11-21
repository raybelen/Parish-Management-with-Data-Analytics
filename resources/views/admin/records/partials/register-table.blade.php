<section class="admin-card min-w-0 overflow-hidden !p-0" aria-labelledby="register-heading">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-navy/10 px-5 py-4">
        <div>
            <h2 id="register-heading" class="text-2xl">Register entries</h2>
            <p class="mt-1 text-sm text-muted">{{ number_format($records->total()) }} {{ Str::plural('record', $records->total()) }}{{ request()->filled('q') ? ' matching your search' : ' in this view' }}</p>
        </div>
        @if($records->isNotEmpty())<p id="register-scroll-help" class="max-w-xs text-xs leading-5 text-muted">Select View details for complete service information and register references.</p>@endif
    </div>
<div class="max-h-[70vh] overflow-auto overscroll-x-contain focus-visible:outline-offset-[-3px]" role="region" aria-label="{{ $serviceType->name }} register" @if($records->isNotEmpty()) aria-describedby="register-scroll-help" @endif tabindex="0">
    @if($records->isEmpty())
        <div class="grid gap-2 px-5 py-12 text-center"><p class="font-semibold">No matching service records.</p><p class="text-sm text-muted">{{ request()->filled('q') ? 'Try a different name or record number, or clear your filters.' : 'Entries will appear here when records are added to this book.' }}</p></div>
    @elseif($serviceType->slug === 'baptism')
        @include('admin.records.partials.baptism-table')
    @elseif(in_array($serviceType->slug, ['wedding', 'funeral', 'confirmation'], true))
        @include('admin.records.partials.compact-service-table')
    @else
    <table class="admin-table">
        <caption class="sr-only">{{ $serviceType->name }} register entries</caption>
        <thead>
            <tr>
                <th scope="col" class="sticky top-0 z-30 min-w-52 bg-slate-50 sm:left-0">Record</th>
                @foreach($columns as $key => $label)<th scope="col" @class(['sticky top-0 z-20', 'min-w-28' => str_ends_with($key, '_year') || str_ends_with($key, '_month_day'), 'min-w-44' => !str_ends_with($key, '_year') && !str_ends_with($key, '_month_day')])>{{ $label }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($records as $record)
                <tr class="group even:bg-slate-50/50">
                    <td class="border-r border-navy/10 bg-white group-hover:bg-slate-50 sm:sticky sm:left-0 sm:z-10">
                        <a class="inline-flex min-h-11 items-center font-semibold underline decoration-gold underline-offset-4" href="{{ route('admin.records.show', $record) }}">{{ $record->record_number }}</a>
                        <p class="max-w-52 whitespace-normal break-words text-xs leading-5 text-muted">{{ $record->participant_names }}</p>
                        @if($record->archived_at)<span class="mt-2 inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-muted">Archived</span>@endif
                    </td>
                    @foreach($columns as $key => $label)
                        @if(in_array($key, ['parents', 'godparents'], true))
                            @php
                                $groups = $key === 'parents'
                                    ? ['Father' => ['father_name' => 'Full name', 'father_origin_address' => 'Origin address'], 'Mother' => ['mother_name' => 'Full name', 'mother_origin_address' => 'Origin address'], 'Current residence' => ['parents_current_residence' => 'Parents’ shared address']]
                                    : ['Godfather' => ['godfather_name' => 'Full name', 'godfather_address' => 'Address'], 'Godmother' => ['godmother_name' => 'Full name', 'godmother_address' => 'Address']];
                            @endphp
                            <td>
                                <div class="grid w-64 gap-3 whitespace-normal break-words">
                                    @foreach($groups as $heading => $fields)
                                        <section @class(['border-t border-navy/10 pt-3' => !$loop->first])>
                                            <h3 class="font-sans text-xs font-semibold tracking-wide text-muted uppercase">{{ $heading }}</h3>
                                            <dl class="mt-1 grid gap-2">
                                                @foreach($fields as $field => $fieldLabel)
                                                    @php($value = \App\Support\ServiceRegister::value($record, $field))
                                                    <div>
                                                        <dt @class(['sr-only' => $fieldLabel === 'Full name', 'text-xs text-muted' => $fieldLabel !== 'Full name'])>{{ $fieldLabel }}</dt>
                                                        <dd @class(['font-medium' => $fieldLabel === 'Full name', 'text-muted' => $value === ''])>{{ $value !== '' ? $value : '—' }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </section>
                                    @endforeach
                                </div>
                            </td>
                        @else
                            @php($value = \App\Support\ServiceRegister::value($record, $key))
                            <td><span class="block max-w-72 whitespace-normal break-words">{{ $value !== '' ? $value : '—' }}</span></td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>
</section>
{{ $records->links() }}
