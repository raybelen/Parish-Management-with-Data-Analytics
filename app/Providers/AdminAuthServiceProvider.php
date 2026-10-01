<?php

namespace App\Providers;

use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Timebox;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;

class AdminAuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Fortify::loginView(fn () => view('admin.login'));
        Fortify::twoFactorChallengeView(fn () => view('admin.challenge'));
        Fortify::authenticateUsing(function (Request $request): ?User {
            return (new Timebox)->call(function () use ($request): ?User {
                $provider = Auth::guard('web')->getProvider();
                $user = $provider->retrieveByCredentials($request->only('email', 'password'));
                if (! $user || ! $provider->validateCredentials($user, $request->only('password')) || ! $user->isAdmin()) {
                    return null;
                }
                $provider->rehashPasswordIfRequired($user, $request->only('password'));

                return $user;
            }, 200000);
        });
        Event::listen(TwoFactorAuthenticationChallenged::class, function (TwoFactorAuthenticationChallenged $event): void {
            request()->session()->regenerate(true);
            request()->session()->put([
                'login.expires_at' => now()->addMinutes(5)->timestamp,
                'login.fingerprint' => $event->user->adminSessionFingerprint(),
            ]);
        });
        Event::listen(ValidTwoFactorAuthenticationCodeProvided::class, function (ValidTwoFactorAuthenticationCodeProvided $event): void {
            request()->session()->put([
                'admin.mfa_user_id' => $event->user->getKey(),
                'admin.mfa_fingerprint' => $event->user->adminSessionFingerprint(),
            ]);
            request()->session()->forget('login');
        });
        Event::listen(Login::class, function (Login $event): void {
            if (! request()->routeIs('two-factor.login.store') && request()->hasSession()) {
                request()->session()->forget('admin');
            }
            if ($event->user->isAdmin()) {
                SecurityAudit::record(request()->routeIs('two-factor.login.store') ? 'admin.login' : 'admin.password_authenticated', $event->user);
            }
        });
        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user?->isAdmin()) {
                SecurityAudit::record('admin.logout', $event->user);
            }
        });
        Event::listen(Failed::class, fn (Failed $event) => SecurityAudit::record('auth.login_failed', $event->user));
        foreach ([
            TwoFactorAuthenticationEnabled::class => 'admin.mfa_enrollment_started',
            TwoFactorAuthenticationConfirmed::class => 'admin.mfa_enrolled',
            TwoFactorAuthenticationDisabled::class => 'admin.mfa_removed',
            TwoFactorAuthenticationFailed::class => 'admin.mfa_failed',
            RecoveryCodesGenerated::class => 'admin.recovery_codes_generated',
            RecoveryCodeReplaced::class => 'admin.recovery_code_used',
        ] as $eventClass => $auditEvent) {
            Event::listen($eventClass, fn (object $event) => SecurityAudit::record($auditEvent, $event->user));
        }

        Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-admin-roles', fn (User $user): bool => $user->role === User::ROLE_SUPER_ADMIN);

        RateLimiter::for('admin-login', function (Request $request): array {
            $email = $request->input('email');
            $identifier = hash('sha256', is_string($email) ? mb_strtolower(trim($email)) : '');

            return [
                Limit::perMinute(20)->by('ip:'.$request->ip()),
                Limit::perMinute(5)->by('account-ip:'.$identifier.'|'.$request->ip()),
                Limit::perMinute(30)->by('account:'.$identifier),
            ];
        });
        RateLimiter::for('admin-mfa', fn (Request $request): array => [
            Limit::perMinute(20)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('user:'.($request->user()?->getKey() ?? $request->session()->get('login.id', $request->ip()))),
        ]);
        RateLimiter::for('admin-sensitive', fn (Request $request) => Limit::perMinute(5)->by((string) $request->user()?->getKey()));
    }
}
