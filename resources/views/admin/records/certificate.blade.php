<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · {{ $record->record_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="certificate-page bg-slate-100 p-4 text-black sm:p-8 print:bg-white print:p-0">
    <nav class="mx-auto mb-5 flex max-w-3xl flex-wrap items-center justify-between gap-3 print:hidden" aria-label="Certificate actions">
        <a class="admin-secondary" href="{{ route('admin.records.show', $record) }}">Back to record</a>
        <button class="admin-button" type="button" data-print-billing>Print / Save PDF</button>
        <p class="w-full text-sm text-muted">A4 portrait. Review the saved details before printing. Blank lines indicate information not recorded. Turn off browser headers and footers in the print dialog.</p>
    </nav>
    <main class="service-certificate mx-auto bg-white">
        <header class="certificate-heading text-center">
            <p class="text-sm font-semibold tracking-widest uppercase">Diocese of Legazpi</p>
            <p class="mt-2 text-lg font-semibold">{{ $parishName }}</p>
            <h1 class="mt-7 text-4xl">{{ $title }}</h1>
            <p class="mt-4 text-sm italic">{{ $record->service_type === 'funeral' ? 'Certified extract from the burial register of this church.' : 'This is to certify the following entry in the sacramental register of this church.' }}</p>
        </header>
        @if($record->source === 'synthetic')<p class="my-4 border-2 border-black p-2 text-center font-bold">SAMPLE — TEST RECORD</p>@endif
        @if($record->archived_at)<p class="my-4 border border-black p-2 text-center text-sm">Archived record — preserved register copy</p>@endif
        <div @class(['certificate-sections', 'certificate-marriage' => $record->service_type === 'wedding'])>
            @foreach($sections as $heading => $rows)
                <section @class(['certificate-section', 'certificate-witnesses' => $heading === 'Witnesses'])>
                    <h2 class="mb-3 font-sans text-xs font-semibold tracking-widest uppercase">{{ $heading }}</h2>
                    <dl>
                        @foreach($rows as $label => $value)
                            <div class="certificate-field"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>
        <section class="certificate-section">
            @if($record->service_type !== 'funeral')
                <h2 class="mb-3 text-center text-xl">{{ match ($record->service_type) { 'baptism' => 'The Holy Sacrament of Baptism', 'wedding' => 'United in Holy Matrimony', default => 'The Sacrament of Confirmation' } }}</h2>
                <p class="mb-4 text-center text-sm italic">According to the rite of the Roman Catholic Church</p>
            @endif
            <dl>
                @if($record->service_type !== 'funeral')<div class="certificate-field"><dt>{{ match ($record->service_type) { 'baptism' => 'Date of baptism', 'wedding' => 'Date of marriage', default => 'Date of confirmation' } }}</dt><dd>{{ $record->service_date?->format('F j, Y') }}</dd></div>@endif
                <div class="certificate-field"><dt>Church / chapel</dt><dd>{{ $record->church }}</dd></div>
                <div class="certificate-field"><dt>{{ $record->service_type === 'funeral' ? 'Certifying minister' : 'Officiating minister' }}</dt><dd>{{ $record->officiant }}</dd></div>
            </dl>
        </section>
        <footer class="certificate-footer">
            <p class="mb-4 text-sm">The above information agrees with the entry in the original register of this church.</p>
            <dl class="grid grid-cols-3 gap-4">
                @foreach(['register_book' => 'Book no.', 'register_page' => 'Page', 'register_entry' => 'Entry no.'] as $field => $label)
                    <div class="certificate-reference"><dt>{{ $label }}</dt><dd>{{ $record->$field }}</dd></div>
                @endforeach
            </dl>
            <dl class="mt-4"><div class="certificate-field"><dt>Date issued</dt><dd>{{ now(config('office.timezone'))->format('F j, Y') }}</dd></div></dl>
            <div class="certificate-signing grid grid-cols-2 items-end gap-10">
                <div class="text-center text-sm">Parish seal</div>
                <div class="border-t border-black pt-2 text-center text-sm">Parish Priest / Parochial Vicar</div>
            </div>
            <p class="mt-8 text-xs text-gray-600">Record: {{ $record->record_number }}</p>
        </footer>
    </main>
</body>
</html>
