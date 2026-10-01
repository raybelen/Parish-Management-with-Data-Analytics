<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorChallenge
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = User::find($request->session()->get('login.id'));
        if (! $user?->isAdmin() || ! $user->hasEnabledTwoFactorAuthentication()
            || (int) $request->session()->get('login.expires_at', 0) <= now()->timestamp
            || $request->session()->get('login.fingerprint') !== $user->adminSessionFingerprint()) {
            $request->session()->forget(['login', 'admin']);

            return redirect()->route('login')->withErrors(['email' => 'Your sign-in attempt expired. Please sign in again.']);
        }

        return $next($request);
    }
}
