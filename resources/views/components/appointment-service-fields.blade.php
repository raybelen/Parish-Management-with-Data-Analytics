@props(['serviceType', 'active' => false, 'inputClass', 'selectClass', 'values' => [], 'preferredDate' => ''])

@foreach (collect(config("appointments.details.{$serviceType}", []))->groupBy('group', preserveKeys: true) as $group => $fields)
    <div class="mt-7">
        <h3 class="mb-4 text-xl">{{ $group }}</h3>
        <div class="grid items-start gap-6 sm:grid-cols-2">
            @foreach ($fields as $key => $field)
                @php
                    $fieldId = $serviceType.'_'.$key;
                    $value = old('service.'.$key, $values[$key] ?? '');
                    $isFullWidthAddress = $field['type'] === 'address' && ! in_array($key, ['godfather_address', 'godmother_address'], true);
                    $mobileGodparentOrder = match ($key) {
                        'godfather_name' => 'order-1 sm:order-none',
                        'godfather_address' => 'order-2 sm:order-none',
                        'godmother_name' => 'order-3 sm:order-none',
                        'godmother_address' => 'order-4 sm:order-none',
                        default => '',
                    };
                    $fieldClass = trim(($isFullWidthAddress ? 'sm:col-span-2 ' : '').$mobileGodparentOrder);
                @endphp
                <x-parish.form-field :id="$fieldId" :name="'service.'.$key" :label="$field['label']" :required="$field['required']" :optional="! $field['required']" :class="$fieldClass">
                    @if ($field['type'] === 'select')
                        <select id="{{ $fieldId }}" name="service[{{ $key }}]" class="{{ $selectClass }}" data-required="{{ $field['required'] ? 'true' : 'false' }}" @required($active && $field['required']) @disabled(! $active)>
                            <option value="">Select an option</option>
                            @foreach ($field['options'] as $option => $label)
                                <option value="{{ $option }}" @selected($value === $option)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @elseif ($field['type'] === 'address')
                        <x-parish.address-field :id="$fieldId" :name="'service['.$key.']'" :label="$field['label']" :value="$value" :required="$field['required']" :active="$active" :input-class="$inputClass" :select-class="$selectClass" />
                    @elseif ($field['type'] === 'textarea')
                        <textarea id="{{ $fieldId }}" name="service[{{ $key }}]" rows="3" maxlength="1000" class="{{ $inputClass }}" data-required="{{ $field['required'] ? 'true' : 'false' }}" @required($active && $field['required']) @disabled(! $active) @if($field['title_case'] ?? false) data-title-case autocapitalize="words" @endif>{{ $value }}</textarea>
                    @else
                        <input id="{{ $fieldId }}" name="service[{{ $key }}]" type="{{ $field['type'] }}" value="{{ $value }}" class="{{ $inputClass }}" data-required="{{ $field['required'] ? 'true' : 'false' }}" @required($active && $field['required']) @disabled(! $active)
                            @if($field['type'] === 'date') max="{{ now()->toDateString() }}" @endif
                            @if($field['type'] === 'number') min="0" max="150" step="1" @endif
                            @if($field['type'] === 'text') maxlength="255" @endif
                            @if($field['title_case'] ?? false) data-title-case autocapitalize="words" @endif>
                    @endif
                </x-parish.form-field>
                @if ($serviceType === 'funeral' && $key === 'date_of_death')
                    <x-parish.form-field id="funeral_year_of_death" name="year_of_death" label="Year of death" help="Filled automatically from the date of death.">
                        <input id="funeral_year_of_death" type="text" value="{{ substr((string) $value, 0, 4) }}" readonly data-death-year class="{{ $inputClass }}" @disabled(! $active)>
                    </x-parish.form-field>
                    <x-parish.form-field id="funeral_burial_date" name="burial_date" label="Date of burial / funeral Mass" help="Uses the preferred date selected in Service Selection.">
                        <input id="funeral_burial_date" type="date" value="{{ old('preferred_date', $preferredDate) }}" readonly data-burial-date class="{{ $inputClass }}" @disabled(! $active)>
                    </x-parish.form-field>
                @endif
            @endforeach
        </div>
    </div>
@endforeach
