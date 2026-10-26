<?php

namespace App\Http\Requests;

use App\Support\OfficeSettings;
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
        return ! $this->routeIs('admin.*') || ($this->user()?->can('appointments.manage') ?? false);
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
        $services = OfficeSettings::services();
        $allowedServices = array_keys(array_filter($services, fn ($service) => $service['active']));
        if ($this->routeIs('admin.appointments.details')) {
            $allowedServices = [$this->route('appointment')->service_type];
        }
        $documentDefinitions = $services[$serviceType]['documents'] ?? [];
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
            'service_type' => ['required', Rule::in($allowedServices)],
            'preferred_date' => ['required', 'date_format:Y-m-d', ...($this->routeIs('admin.appointments.details') ? [] : ['after_or_equal:today'])],
            'preferred_time' => ['required', 'date_format:H:i'],
            'preferred_church' => ['required', Rule::in($this->allowedVenues())],
            'baptism_mode' => ['nullable', Rule::in(['private'])],
            'group_baptism_session_id' => ['prohibited'],
            'additional_notes' => ['nullable', 'string', 'max:2000'],
            'version' => [$this->routeIs('admin.appointments.details') ? 'required' : 'nullable', 'integer'],
            'change_reason' => [$this->routeIs('admin.appointments.details') ? 'required' : 'nullable', 'string', 'max:1000'],
            'service' => $serviceRules === []
                ? ['required', 'array']
                : ['required', 'array:'.implode(',', $serviceKeys)],
            ...$serviceRules,
        ];

        if ($documentKeys !== []) {
            $rules['documents'] = [
                'nullable',
                'array:'.implode(',', $documentKeys),
            ];

            foreach ($documentDefinitions as $documentKey => $definition) {
                $rules["documents.{$documentKey}"] = [
                    'nullable',
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
        $fields = config("appointments.details.{$serviceType}");

        if ($fields === null) {
            return ['service.recipient_full_name' => ['required', 'string', 'max:255']];
        }

        $rules = [];

        foreach ($fields as $key => $field) {
            $rules["service.{$key}"] = $field['rules'];
        }

        if ($serviceType === 'funeral') {
            $rules['service.date_of_death'][] = 'before_or_equal:preferred_date';
        }

        return $rules;
    }

    /**
     * @return array<int, string>
     */
    private function phoneRules(): array
    {
        return ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/'];
    }

    /** @return array<int, string> */
    private function allowedVenues(): array
    {
        $venues = OfficeSettings::activeVenues();

        if ($this->routeIs('admin.appointments.details')) {
            $currentVenue = $this->route('appointment')->preferred_church;

            if (! in_array($currentVenue, $venues, true)) {
                $venues[] = $currentVenue;
            }
        }

        return $venues;
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
            'preferred_church.in' => 'The selected church or chapel is currently unavailable.',
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

        foreach (config('appointments.details.'.$this->string('service_type')->toString(), []) as $key => $definition) {
            $attributes["service.{$key}"] = mb_strtolower($definition['label']);
        }

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

            foreach (['cause_of_death'] as $field) {
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
        $fields = config('appointments.details.'.$this->string('service_type')->toString(), []);

        return array_keys(array_filter($fields, fn (array $field): bool => $field['title_case'] ?? false));
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
