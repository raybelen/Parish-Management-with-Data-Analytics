<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payments.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'kind' => ['required', Rule::in(['payment', 'refund'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999', 'regex:/^\d+(\.\d{1,2})?$/'],
            'original_payment_id' => ['required_if:kind,refund', 'nullable', 'integer'],
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'gcash', 'check', 'other'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['required', 'date_format:Y-m-d\TH:i', 'before_or_equal:now'],
            'notes' => ['required', 'string', 'max:1000'],
        ];
    }
}
