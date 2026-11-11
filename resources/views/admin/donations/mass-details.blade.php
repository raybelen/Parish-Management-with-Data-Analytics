<dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2 print:grid-cols-2">
    @foreach(['Church / chapel' => $mass->church, 'Mass time' => $mass->mass_time, 'Collection type' => \App\Models\Donation::COLLECTION_TYPES[$mass->collection_type], 'Special purpose' => $mass->collection_purpose, 'Counted by' => $mass->counted_by, 'Checked by' => $mass->verified_by] as $label => $value)
        @if(filled($value))<div class="min-w-0"><dt class="text-muted">{{ $label }}</dt><dd class="mt-1 break-words">{{ $value }}</dd></div>@endif
    @endforeach
</dl>
