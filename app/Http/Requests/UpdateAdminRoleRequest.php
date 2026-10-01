<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-admin-roles');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in([User::ROLE_USER, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])],
            'password' => ['required', 'string', 'current_password:web'],
        ];
    }
}
