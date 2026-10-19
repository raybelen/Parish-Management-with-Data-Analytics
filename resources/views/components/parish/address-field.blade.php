@props([
    'id',
    'name',
    'label' => 'Address',
    'value' => '',
    'required' => false,
    'active' => true,
    'inputClass',
    'selectClass',
])

<input
    type="hidden"
    name="{{ $name }}"
    value="{{ $value }}"
    data-psgc-address-output
    data-required="{{ $required ? 'true' : 'false' }}"
    data-required-label="{{ $label }}"
    @disabled(! $active)
>

<div class="overflow-hidden border border-navy/15 bg-white" data-psgc-address data-api-base="https://psgc.cloud/api/v2">
    <fieldset class="grid gap-4 p-4 sm:grid-cols-2" data-psgc-address-controls @disabled(! $active)>
        <label class="grid content-start gap-2 text-sm text-navy">
            <span class="font-medium">Region</span>
            <select class="{{ $selectClass }}" data-psgc-region>
                <option value="">Loading regions...</option>
            </select>
        </label>

        <label class="grid content-start gap-2 text-sm text-navy">
            <span class="font-medium">Province</span>
            <select class="{{ $selectClass }}" data-psgc-province disabled>
                <option value="">Select a region first</option>
            </select>
        </label>

        <label class="grid content-start gap-2 text-sm text-navy">
            <span class="font-medium">City / Municipality</span>
            <select class="{{ $selectClass }}" data-psgc-city disabled>
                <option value="">Select a province first</option>
            </select>
        </label>

        <label class="grid content-start gap-2 text-sm text-navy">
            <span class="font-medium">Barangay</span>
            <select class="{{ $selectClass }}" data-psgc-barangay data-required="{{ $required ? 'true' : 'false' }}" @required($required && blank($value)) disabled>
                <option value="">Select a city or municipality first</option>
            </select>
        </label>

        <label class="grid content-start gap-2 text-sm text-navy sm:col-span-2">
            <span class="font-medium">Street / Sitio / Purok <span class="font-normal text-muted">(optional)</span></span>
            <input type="text" maxlength="255" autocomplete="address-line1" autocapitalize="words" data-title-case data-psgc-address-detail class="{{ $inputClass }}" placeholder="Enter the street, sitio, or purok">
        </label>

        <div class="border-l-2 border-gold bg-gold/10 px-4 py-3 sm:col-span-2">
            <p class="text-[10px] font-semibold tracking-[0.12em] text-[#80662e] uppercase">Selected address</p>
            <output id="{{ $id }}" class="mt-1 block min-h-5 text-sm leading-6 text-navy" data-psgc-address-preview>{{ $value ?: 'Select your region, province, city or municipality, and barangay to complete the address.' }}</output>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 sm:col-span-2">
            <p class="text-xs leading-5 text-muted" aria-live="polite" data-psgc-status>Loading address options...</p>
            <button type="button" hidden class="min-h-10 border border-navy/20 bg-white px-4 py-2 text-xs font-medium text-navy transition-colors hover:border-gold hover:bg-gold/10" data-psgc-retry>Retry loading</button>
        </div>
    </fieldset>
</div>
