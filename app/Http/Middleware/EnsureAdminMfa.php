<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminMfa
{
    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $user = $request->user();
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return $mode === 'enrollment' ? $next($request) : redirect()->route('admin.security');
        }
        if ($request->session()->get('admin.mfa_user_id') !== $user->getKey()
            || $request->session()->get('admin.mfa_fingerprint') !== $user->adminSessionFingerprint()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Please sign in and complete two-factor authentication.']);
        }

        return $next($request);
    }
}
