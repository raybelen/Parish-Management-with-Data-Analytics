<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

class AdminTwoFactorSessionController extends TwoFactorAuthenticatedSessionController
{
    public function store(TwoFactorLoginRequest $request): mixed
    {
        return DB::transaction(function () use ($request): mixed {
            $user = User::query()->lockForUpdate()->findOrFail($request->session()->get('login.id'));
            abort_unless($user->isAdmin() && $user->hasEnabledTwoFactorAuthentication()
                && $request->session()->get('login.fingerprint') === $user->adminSessionFingerprint(), 403);

            return parent::store($request);
        });
    }
}
