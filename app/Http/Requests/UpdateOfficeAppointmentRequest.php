<?php

namespace App\Http\Requests;

use App\Support\OfficeSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOfficeAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('appointments.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'duration_minutes' => ['required', 'integer', Rule::in([60])],
            'buffer_minutes' => ['required', 'integer', 'between:0,240'],
            'confirmed_church' => ['required', Rule::in(OfficeSettings::activeVenues())],
            'personnel_id' => ['required', 'integer', 'exists:personnel,id'],
            'office_notes' => ['nullable', 'string', 'max:4000'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'duration_minutes.in' => 'All parish services must use the standard 60-minute duration.',
            'confirmed_church.in' => 'Choose a church or chapel that is currently available.',
        ];
    }
}
