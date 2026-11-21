<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecoverFacilityBookingRequest extends FormRequest
{
    /** @var string */
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
            'organizer' => ['required', 'string', 'max:150'],
            'contact_email' => ['required', 'email', 'max:254'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'organizer' => preg_replace('/\s+/u', ' ', trim((string) $this->input('organizer'))) ?? '',
            'contact_email' => mb_strtolower(trim((string) $this->input('contact_email'))),
        ]);
    }
}
