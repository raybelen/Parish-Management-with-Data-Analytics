<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FindFacilityBookingRequest extends FormRequest
{
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
            'reference' => ['required', 'string', 'regex:/^FAC-[A-Z0-9]{16}$/'],
            'contact_email' => ['required', 'email', 'max:254'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference' => mb_strtoupper(trim((string) $this->input('reference'))),
            'contact_email' => mb_strtolower(trim((string) $this->input('contact_email'))),
        ]);
    }
}
