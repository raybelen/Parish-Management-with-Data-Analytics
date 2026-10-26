<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('schedules.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $start = $this->input('starts_at');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'location' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'group_capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'group_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'category' => ['required', 'string', 'max:80'],
            'personnel_id' => ['nullable', 'integer', Rule::exists('personnel', 'id')->where('active', true)],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:1900-01-01', 'before:2101-01-01'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', Rule::when(is_string($start), 'after:starts_at'), 'before:2102-01-01'],
            'status' => ['required', Rule::in(['scheduled', 'completed', 'cancelled'])],
            'recurrence' => [$this->isMethod('POST') ? 'required' : 'prohibited', Rule::in(['none', 'daily', 'weekly', 'monthly', 'yearly'])],
            'recurrence_until' => [$this->isMethod('POST') ? 'nullable' : 'prohibited', Rule::requiredIf($this->isMethod('POST') && $this->input('recurrence', 'none') !== 'none'), 'date_format:Y-m-d', 'after_or_equal:'.(is_string($start) ? substr($start, 0, 10) : '1900-01-01'), 'before:2101-01-01'],
            'version' => [$this->isMethod('POST') ? 'prohibited' : 'required', 'integer', 'min:1'],
        ];
    }
}
