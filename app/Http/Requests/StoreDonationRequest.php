<?php

namespace App\Http\Requests;

use App\Models\Donation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDonationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $donation = $this->route('donation');
        $kind = $donation instanceof Donation ? $donation->kind : $this->input('kind', 'individual');
        $this->merge(['kind' => $kind]);
        if ($donation instanceof Donation) {
            $this->merge(['mass_collection_id' => $donation->mass_collection_id]);
        }
        if ($kind === 'mass_collection') {
            $identity = [
                'donor_name' => 'Mass congregation',
                'donor_email' => null, 'donor_phone' => null, 'donor_address' => null,
            ];
            $this->merge($identity);
            // Validation errors flash the original HTTP request, not this FormRequest.
            request()->merge($identity);
        }
        if ($kind === 'mass_collection' || $this->filled('mass_collection_id')) {
            $this->merge(['category' => 'mass_offering']);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('donations.view') && $this->user()->can('donations.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['individual', 'mass_collection'])],
            'is_anonymous' => ['sometimes', 'boolean', Rule::in([false, 0, '0'])],
            'mass_collection_id' => ['nullable', 'integer', 'min:1', 'prohibited_if:kind,mass_collection'],
            'idempotency_key' => ['required', 'uuid'],
            'donor_name' => ['required', 'string', 'max:255'],
            'donor_email' => ['nullable', 'email', 'max:255'],
            'donor_phone' => ['nullable', 'string', 'max:40'],
            'donor_address' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'donated_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now(config('office.timezone'))->toDateString()],
            'category' => ['required', Rule::in(array_keys(Donation::CATEGORIES))],
            'method' => ['required', Rule::in(array_keys(Donation::METHODS))],
            'reference' => ['nullable', 'required_unless:method,cash', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'version' => [$this->routeIs('admin.donations.update') ? 'required' : 'sometimes', 'integer', 'min:1'],
            'reason' => [$this->routeIs('admin.donations.update') ? 'required' : 'sometimes', 'string', 'max:1000'],
            'church' => ['exclude_unless:kind,mass_collection', 'required', 'string', Rule::in(config('parish.churches'))],
            'mass_time' => ['exclude_unless:kind,mass_collection', 'required', 'date_format:H:i'],
            'collection_type' => ['exclude_unless:kind,mass_collection', 'required', Rule::in(array_keys(Donation::COLLECTION_TYPES))],
            'collection_purpose' => ['exclude_unless:kind,mass_collection', 'nullable', 'required_if:collection_type,special', 'string', 'max:255'],
            'counted_by' => ['exclude_unless:kind,mass_collection', 'required', 'string', 'max:255'],
            'verified_by' => ['exclude_unless:kind,mass_collection', 'required', 'string', 'max:255'],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->input('kind') !== 'mass_collection') {
                return;
            }
            if (mb_strtolower(trim($this->input('counted_by'))) === mb_strtolower(trim($this->input('verified_by')))) {
                $validator->errors()->add('verified_by', 'A different person must verify the collection count.');
            }
            if (CarbonImmutable::parse($this->input('donated_on').' '.$this->input('mass_time'), config('office.timezone'))->isFuture()) {
                $validator->errors()->add('mass_time', 'Record a collection only after its Mass has started.');
            }
        }];
    }
}
