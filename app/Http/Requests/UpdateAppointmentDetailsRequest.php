<?php

namespace App\Http\Requests;

use Illuminate\Support\Arr;

class UpdateAppointmentDetailsRequest extends StoreAppointmentRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('appointments.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $editable = ['client_full_name', 'client_contact_number', 'client_email', 'client_address', 'preferred_contact_method', 'additional_notes', 'version', 'change_reason', 'service'];
        $serviceKeys = array_keys(config('appointments.details.'.$this->serviceType(), ['recipient_full_name' => []]));
        $lockedServiceKeys = $this->serviceType() === 'others' ? ['service_kind', 'service_location'] : [];
        $serviceKeys = array_values(array_diff($serviceKeys, $lockedServiceKeys));
        $rules = Arr::only($rules, [...$editable, ...array_map(fn (string $key): string => 'service.'.$key, $serviceKeys)]);
        $rules['service'] = ['required', 'array:'.implode(',', $serviceKeys)];
        $rules['documents_checked'] = ['accepted'];

        foreach (['service_type', 'preferred_date', 'preferred_time', 'preferred_church', 'starts_at', 'ends_at', 'confirmed_church', 'personnel_id', 'group_baptism_session_id', 'baptism_mode', 'fee_cents', 'offering_amount', 'payment_option', 'payment_status', 'status', 'documents', 'physical_documents'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        if ($this->serviceType() === 'funeral') {
            $rules['service.date_of_death'] = [
                ...config('appointments.details.funeral.date_of_death.rules'),
                'before_or_equal:'.$this->route('appointment')->preferred_date->toDateString(),
            ];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'documents_checked.accepted' => 'Confirm that the corrected details match the documents before saving.',
            'change_reason.required' => 'Enter a reason for this correction.',
        ];
    }

    protected function serviceType(): string
    {
        return $this->route('appointment')->service_type;
    }
}
