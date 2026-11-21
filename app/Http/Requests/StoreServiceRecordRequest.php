<?php

namespace App\Http\Requests;

use App\Support\ServiceRegister;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRecordRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $nameFields = match ($this->input('service_type')) {
            'baptism' => ['child_full_name'], 'wedding' => ['groom_full_name', 'bride_full_name'],
            'funeral' => ['deceased_full_name'], 'confirmation' => ['recipient_full_name'], default => [],
        };
        $names = [];
        foreach ($nameFields as $field) {
            $name = $this->input('register_details.'.$field);
            if (! is_string($name) || blank($name)) {
                return;
            }
            $names[] = $name;
        }
        if ($names !== []) {
            $this->merge(['participant_names' => implode(' & ', $names)]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('records.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $fields = ServiceRegister::fields($this->string('service_type')->toString());
        $detailRules = [];
        foreach ($fields as $key => $field) {
            $detailRules['register_details.'.$key] = ['nullable', ...array_values(array_diff($field['rules'], ['required', 'nullable']))];
        }

        return [
            'register_details' => $fields === [] ? ['prohibited'] : ['nullable', 'array:'.implode(',', array_keys($fields))],
            ...$detailRules,
            'record_number' => ['required', 'string', 'max:100', Rule::unique('service_records')->ignore($this->route('record'))],
            'service_type' => ['required', 'string', 'exists:service_types,slug'],
            'service_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'participant_names' => ['required', 'string', 'max:255'],
            'church' => ['required', 'string', 'max:255'], 'officiant' => ['nullable', 'string', 'max:255'],
            'register_book' => ['nullable', 'string', 'max:100'], 'register_page' => ['nullable', 'string', 'max:100'],
            'register_entry' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:4000'],
            'details_text' => ['nullable', 'string', 'max:8000'],
            'reason' => ['required', 'string', 'max:1000'],
            'version' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer'],
        ];
    }
}
