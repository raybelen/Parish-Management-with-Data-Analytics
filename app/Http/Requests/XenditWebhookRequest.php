<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class XenditWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expectedToken = (string) config('services.xendit.sandbox_webhook_token');
        $receivedToken = (string) $this->header('X-Callback-Token');

        return (bool) config('services.xendit.sandbox_enabled')
            && $expectedToken !== ''
            && hash_equals($expectedToken, $receivedToken);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isDashboardTest = $this->input('data.reference_id') === 'test_session';

        return [
            'event' => ['required', Rule::in(['payment_session.completed', 'payment_session.expired'])],
            'business_id' => ['required', 'string', 'max:255'],
            'created' => ['required', 'date'],
            'data' => ['required', 'array'],
            'data.reference_id' => ['required', 'string', 'max:64'],
            'data.payment_session_id' => [Rule::requiredIf(! $isDashboardTest), 'nullable', 'string', 'max:255'],
            'data.payment_id' => [Rule::requiredIf(! $isDashboardTest && $this->input('event') === 'payment_session.completed'), 'nullable', 'string', 'max:255'],
            'data.amount' => [Rule::requiredIf(! $isDashboardTest), 'nullable', 'integer', 'min:1'],
            'data.currency' => [Rule::requiredIf(! $isDashboardTest), 'nullable', Rule::when(! $isDashboardTest, Rule::in(['PHP']), ['string', 'size:3'])],
            'data.status' => ['required', Rule::in(['COMPLETED', 'EXPIRED'])],
            'data.updated' => ['nullable', 'date'],
            'data.expires_at' => ['nullable', 'date'],
            'data.channel_code' => ['nullable', 'string', 'max:60'],
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json(['message' => 'Invalid Xendit webhook token.'], 401));
    }
}
