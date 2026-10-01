<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminMfaRequest;
use App\Http\Requests\ConfirmAdminMfaRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Fortify;

class AdminSecurityController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $enabled = $user->hasEnabledTwoFactorAuthentication();
        $pending = ! $enabled && $user->two_factor_secret;

        return view('admin.security', [
            'enabled' => $enabled,
            'qrCode' => $pending ? $user->twoFactorQrCodeSvg() : null,
            'setupKey' => $pending ? Fortify::currentEncrypter()->decrypt($user->two_factor_secret) : null,
            'recoveryCodes' => $enabled && $request->session()->pull('admin.show_recovery_codes', false) ? $user->recoveryCodes() : [],
        ]);
    }

    public function store(AdminMfaRequest $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        DB::transaction(function () use ($request, $enable): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            abort_if($user->hasEnabledTwoFactorAuthentication(), 409);
            $enable($user);
        });
        $request->user()->refresh();

        return redirect()->route('admin.security');
    }

    public function confirm(ConfirmAdminMfaRequest $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $user = DB::transaction(function () use ($request, $confirm): User {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            abort_if($user->hasEnabledTwoFactorAuthentication(), 409);
            $confirm($user, $request->validated('code'));

            return $user;
        });
        $request->user()->refresh();
        $request->session()->regenerate(true);
        $request->session()->put([
            'admin.mfa_user_id' => $user->getKey(),
            'admin.mfa_fingerprint' => $user->adminSessionFingerprint(),
            'admin.show_recovery_codes' => true,
        ]);

        return redirect()->route('admin.security')->with('status', 'Authenticator confirmed. Save your recovery codes now.');
    }

    public function recoveryCodes(AdminMfaRequest $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        DB::transaction(function () use ($request, $generate): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            $generate($user);
        });
        $request->user()->refresh();
        $request->session()->put('admin.show_recovery_codes', true);

        return redirect()->route('admin.security')->with('status', 'New recovery codes generated. Previous codes no longer work.');
    }
}
