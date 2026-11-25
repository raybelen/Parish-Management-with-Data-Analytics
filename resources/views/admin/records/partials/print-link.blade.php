@if($record->exists && isset(\App\Support\ServiceCertificate::TITLES[$record->service_type]))
    <a class="admin-secondary whitespace-nowrap" href="{{ route('admin.records.certificate', $record) }}" target="_blank" rel="noopener" aria-label="Print Certificate for {{ $record->participant_names }} (opens in a new tab)">Print Certificate</a>
@endif
