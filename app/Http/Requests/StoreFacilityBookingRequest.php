<?php

namespace App\Http\Requests;

use App\Models\Facility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

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
        $facility = $this->route('facility');
        $capacity = $facility instanceof Facility ? $facility->capacity : 100000;

        return [
            'title' => ['required', 'string', 'max:150'],
            'organizer' => ['required', 'string', 'max:150'],
            'contact_email' => ['required', 'email', 'max:254'],
            'activity' => ['required', 'string', 'max:100'],
            'attendees' => ['required', 'integer', 'min:1', 'max:'.$capacity],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:'.now()->addHours((int) config('facilities.approval_cutoff_hours'))->format('Y-m-d H:i:s')],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'attendees.max' => 'This facility can accommodate up to :max people. Reduce the attendee count before submitting.',
            'starts_at.after' => 'Online facility requests must be submitted more than 24 hours before the requested start time.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'organizer' => Str::squish((string) $this->input('organizer')),
            'contact_email' => mb_strtolower(trim((string) $this->input('contact_email'))),
        ]);
    }
}
