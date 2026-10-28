<?php

namespace App\Providers;

use App\Support\WebsiteContent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as IlluminateView;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        TrustProxies::at(config('security.trusted_proxies', []));
        View::share('navigation', config('parish.navigation'));
        View::composer('client-side.layouts.parish', function (IlluminateView $view): void {
            $view->with('websiteContent', WebsiteContent::values());
        });

        RateLimiter::for('appointment-submissions', function (Request $request): array {
            $email = $request->input('client_email');
            $contact = is_string($email) && trim($email) !== ''
                ? $email
                : $request->input('client_contact_number');
            $contactIdentifier = hash('sha256', is_string($contact) ? mb_strtolower(trim($contact)) : '');
            $ipAddress = $request->ip();

            return [
                Limit::perMinute(5)->by('appointment-minute-ip:'.$ipAddress),
                Limit::perHour(30)->by('appointment-hour-ip:'.$ipAddress),
                Limit::perHour(10)->by('appointment-contact-ip:'.$contactIdentifier.'|'.$ipAddress),
            ];
        });
    }
}
