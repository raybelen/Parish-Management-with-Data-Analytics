<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminMfaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['password' => ['required', 'string', 'current_password:web']];
    }
}
