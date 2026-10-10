<?php

namespace App\Http\Requests;

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
            'duration_minutes' => ['required', 'integer', 'between:15,480'],
            'buffer_minutes' => ['required', 'integer', 'between:0,240'],
            'confirmed_church' => ['required', Rule::in(config('parish.churches'))],
            'personnel_id' => ['required', 'integer', 'exists:personnel,id'],
            'office_notes' => ['nullable', 'string', 'max:4000'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
