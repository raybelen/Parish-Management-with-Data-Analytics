<?php

namespace App\Http\Requests;

use App\Models\Facility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFacilityBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $facility = $this->route('facility');
        abort_if($facility instanceof Facility && ($facility->source !== 'operational' || ! $facility->active), 404);

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
            'title' => ['required', 'string', 'max:150'],
            'organizer' => ['required', 'string', 'max:150'],
            'contact_email' => ['required', 'email', 'max:254'],
            'activity' => ['required', 'string', 'max:100'],
            'attendees' => ['required', 'integer', 'min:1', 'max:100000'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'contact_email' => mb_strtolower(trim((string) $this->input('contact_email'))),
        ]);
    }
}
