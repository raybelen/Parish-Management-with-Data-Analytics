<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Support\OfficeSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $appointment instanceof Appointment && $appointment->status === 'pending';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'group_baptism_session_id' => ['prohibited'],
            'preferred_church' => [$this->route('appointment')->group_baptism_session_id ? 'sometimes' : 'prohibited', $this->route('appointment')->group_baptism_session_id ? 'required' : 'nullable', Rule::in(OfficeSettings::activeVenues())],
            'preferred_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'preferred_time' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'preferred_date.after_or_equal' => 'The preferred date must be today or a future date.',
            'preferred_time.date_format' => 'Please enter a valid preferred time.',
        ];
    }
}
