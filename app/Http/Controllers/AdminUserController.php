<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-admin-roles');

        return view('admin.users', ['users' => User::query()->orderBy('name')->orderBy('id')->paginate(25)]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-admin-roles');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users'],
            'role' => ['required', Rule::in(['staff', 'priest', 'admin', 'super_admin'])],
            'new_password' => ['required', 'string', 'max:72', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'password' => ['required', 'current_password:web'],
        ]);
        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['new_password']]);
        $user->role = $data['role'];
        $user->save();

        return back()->with('status', 'Account created. The user must enroll in two-factor authentication at first sign-in.');
    }
}
