<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('records.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
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
