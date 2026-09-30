<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecoverAppointmentReferenceRequest extends FormRequest
{
    /**
     * The error bag used for recovery form validation errors.
     *
     * @var string
     */
    protected $errorBag = 'recovery';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recovery_full_name' => ['required', 'string', 'max:255'],
            'recovery_contact_information' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recovery_full_name.required' => 'Please enter the full name used on the appointment request.',
            'recovery_contact_information.required' => 'Please enter the email address or contact number used on the request.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $fullName = preg_replace('/\s+/u', ' ', trim((string) $this->input('recovery_full_name'))) ?? '';

        $this->merge([
            'recovery_full_name' => $fullName,
            'recovery_contact_information' => mb_strtolower(trim((string) $this->input('recovery_contact_information'))),
        ]);
    }
}
