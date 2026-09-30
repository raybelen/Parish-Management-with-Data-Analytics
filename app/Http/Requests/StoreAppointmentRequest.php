<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
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
        $serviceType = $this->string('service_type')->toString();
        $serviceRules = $this->serviceRules($serviceType);
        $serviceKeys = array_map(
            fn (string $key): string => mb_substr($key, mb_strlen('service.')),
            array_keys($serviceRules),
        );
        $documentDefinitions = config("appointments.services.{$serviceType}.documents", []);
        $documentKeys = array_keys($documentDefinitions);

        $rules = [
            'client_full_name' => ['required', 'string', 'max:255'],
            'client_contact_number' => $this->phoneRules(),
            'client_email' => [
                Rule::requiredIf($this->input('preferred_contact_method') === 'email'),
                'nullable',
                Rule::email()->rfcCompliant(strict: false),
                'max:255',
            ],
            'client_address' => ['required', 'string', 'max:1000'],
            'preferred_contact_method' => ['required', Rule::in(['phone', 'sms', 'email'])],
            'service_type' => ['required', Rule::in(array_keys(config('appointments.services', [])))],
            'preferred_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'preferred_time' => ['required', 'date_format:H:i'],
            'preferred_church' => ['required', Rule::in(config('parish.churches', []))],
            'additional_notes' => ['nullable', 'string', 'max:2000'],
            'service' => $serviceRules === []
                ? ['required', 'array']
                : ['required', 'array:'.implode(',', $serviceKeys)],
            ...$serviceRules,
        ];

        if ($documentKeys !== []) {
            $hasRequiredDocuments = collect($documentDefinitions)->contains('required', true);
            $rules['documents'] = [
                $hasRequiredDocuments ? 'required' : 'nullable',
                'array:'.implode(',', $documentKeys),
            ];

            foreach ($documentDefinitions as $documentKey => $definition) {
                $rules["documents.{$documentKey}"] = [
                    $definition['required'] ? 'required' : 'nullable',
                    'array',
                    'max:5',
                ];
                $rules["documents.{$documentKey}.*"] = [
                    'file',
                    'mimes:pdf,jpg,jpeg,png',
                    'max:5120',
                ];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function serviceRules(string $serviceType): array
    {
        return match ($serviceType) {
            'baptism' => [
                'service.child_full_name' => ['required', 'string', 'max:255'],
                'service.child_date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'service.child_place_of_birth' => ['required', 'string', 'max:255'],
                'service.child_sex' => ['required', Rule::in(['male', 'female'])],
                'service.father_name' => ['required', 'string', 'max:255'],
                'service.mother_name' => ['required', 'string', 'max:255'],
                'service.godfather_name' => ['required', 'string', 'max:255'],
                'service.godmother_name' => ['required', 'string', 'max:255'],
                'service.additional_godparents' => ['nullable', 'string', 'max:1000'],
                'service.special_requests' => ['nullable', 'string', 'max:2000'],
                'service.notes' => ['nullable', 'string', 'max:2000'],
            ],
            'wedding' => [
                'service.bride_full_name' => ['required', 'string', 'max:255'],
                'service.groom_full_name' => ['required', 'string', 'max:255'],
                'service.bride_contact_number' => $this->phoneRules(),
                'service.groom_contact_number' => $this->phoneRules(),
                'service.current_address' => ['required', 'string', 'max:1000'],
                'service.expected_guest_count' => ['required', 'integer', 'min:1', 'max:5000'],
                'service.civil_status' => ['required', Rule::in(['single', 'widowed', 'annulled'])],
                'service.previous_marriage' => ['nullable', 'string', 'max:2000'],
                'service.marriage_license_status' => ['required', Rule::in(['not_started', 'in_process', 'secured'])],
                'service.preferred_priest' => ['nullable', 'string', 'max:255'],
                'service.principal_sponsors' => ['nullable', 'string', 'max:2000'],
                'service.witnesses' => ['nullable', 'string', 'max:1000'],
            ],
            'funeral' => [
                'service.deceased_full_name' => ['required', 'string', 'max:255'],
                'service.date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:service.date_of_death'],
                'service.date_of_death' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'service.place_of_death' => ['required', 'string', 'max:255'],
                'service.age' => ['required', 'integer', 'min:0', 'max:150'],
                'service.residence_address' => ['required', 'string', 'max:1000'],
                'service.funeral_home' => ['required', 'string', 'max:255'],
                'service.wake_location' => ['required', 'string', 'max:1000'],
                'service.cemetery_location' => ['required', 'string', 'max:1000'],
                'service.expected_attendee_count' => ['nullable', 'integer', 'min:1', 'max:10000'],
                'service.contact_full_name' => ['required', 'string', 'max:255'],
                'service.contact_relationship' => ['required', 'string', 'max:100'],
                'service.contact_number' => $this->phoneRules(),
                'service.contact_email' => ['nullable', Rule::email()->rfcCompliant(strict: false), 'max:255'],
                'service.contact_address' => ['required', 'string', 'max:1000'],
                'service.special_requests' => ['nullable', 'string', 'max:2000'],
                'service.priest_notes' => ['nullable', 'string', 'max:2000'],
            ],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function phoneRules(): array
    {
        return ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_email.required' => 'An email address is required when email is your preferred contact method.',
            'client_email.email' => 'Please enter a valid email address, such as name@example.com.',
            'service.contact_email.email' => 'Please enter a valid contact email address.',
            'client_contact_number.regex' => 'Please enter a valid contact number with 7 to 15 digits.',
            'service.bride_contact_number.regex' => 'Please enter a valid bride contact number with 7 to 15 digits.',
            'service.groom_contact_number.regex' => 'Please enter a valid groom contact number with 7 to 15 digits.',
            'service.contact_number.regex' => 'Please enter a valid contact number with 7 to 15 digits.',
            'preferred_date.after_or_equal' => 'The preferred date must be today or a future date.',
            'documents.*.required' => 'Please upload the required :attribute.',
            'documents.*.*.mimes' => 'Each document must be a PDF, JPG, or PNG file.',
            'documents.*.*.max' => 'Each document must not be larger than 5 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'client_full_name' => 'full name',
            'client_contact_number' => 'contact number',
            'client_email' => 'email address',
            'client_address' => 'address',
            'preferred_church' => 'preferred church or chapel',
            'service.child_date_of_birth' => "child's date of birth",
            'service.child_place_of_birth' => "child's place of birth",
            'service.expected_guest_count' => 'expected number of guests',
            'service.expected_attendee_count' => 'expected number of attendees',
        ];

        foreach (config('appointments.services.'.$this->string('service_type')->toString().'.documents', []) as $key => $definition) {
            $attributes["documents.{$key}"] = mb_strtolower($definition['label']);
            $attributes["documents.{$key}.*"] = mb_strtolower($definition['label']);
        }

        return $attributes;
    }

    protected function prepareForValidation(): void
    {
        $service = $this->input('service');

        if (is_array($service)) {
            foreach ($this->serviceTitleFields() as $field) {
                if (array_key_exists($field, $service)) {
                    $service[$field] = $this->normalizeTitle($service[$field]);
                }
            }

            foreach (['bride_contact_number', 'groom_contact_number', 'contact_number'] as $field) {
                if (array_key_exists($field, $service)) {
                    $service[$field] = $this->normalizePhone($service[$field]);
                }
            }

            if (array_key_exists('contact_email', $service)) {
                $service['contact_email'] = $this->normalizeEmail($service['contact_email']);
            }

            foreach (['special_requests', 'notes', 'previous_marriage', 'priest_notes'] as $field) {
                if (array_key_exists($field, $service)) {
                    $service[$field] = $this->normalizeText($service[$field]);
                }
            }
        }

        $this->merge([
            'client_full_name' => $this->normalizeTitle($this->input('client_full_name')),
            'client_contact_number' => $this->normalizePhone($this->input('client_contact_number')),
            'client_email' => $this->normalizeEmail($this->input('client_email')),
            'client_address' => $this->normalizeTitle($this->input('client_address')),
            'additional_notes' => $this->normalizeText($this->input('additional_notes')),
            'service' => $service,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function serviceTitleFields(): array
    {
        return [
            'child_full_name',
            'child_place_of_birth',
            'father_name',
            'mother_name',
            'godfather_name',
            'godmother_name',
            'additional_godparents',
            'bride_full_name',
            'groom_full_name',
            'current_address',
            'preferred_priest',
            'principal_sponsors',
            'witnesses',
            'deceased_full_name',
            'place_of_death',
            'residence_address',
            'funeral_home',
            'wake_location',
            'cemetery_location',
            'contact_full_name',
            'contact_relationship',
            'contact_address',
        ];
    }

    private function normalizePhone(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\+?[0-9()\-\s.]+$/', $value) !== 1) {
            return $value;
        }

        $hasInternationalPrefix = str_starts_with($value, '+');
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return ($hasInternationalPrefix ? '+' : '').$digits;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_strtolower(trim($value));
    }

    private function normalizeTitle(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::title($this->collapseWhitespace($value));
    }

    private function normalizeText(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function collapseWhitespace(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }
}
