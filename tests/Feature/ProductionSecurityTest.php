<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductionSecurityTest extends TestCase
{
    public function test_production_auth_pages_send_secure_cookies_and_security_headers(): void
    {
        $this->withoutVite();
        $this->app->instance('env', 'production');
        $originalEnv = $_ENV;
        $originalServer = $_SERVER;
        try {
            $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
            $_ENV['APP_DEBUG'] = $_SERVER['APP_DEBUG'] = 'true';
            $_ENV['SESSION_SECURE_COOKIE'] = $_SERVER['SESSION_SECURE_COOKIE'] = 'false';
            config(['app.debug' => (require config_path('app.php'))['debug']]);
            config(['session.secure' => (require config_path('session.php'))['secure']]);
        } finally {
            $_ENV = $originalEnv;
            $_SERVER = $originalServer;
        }

        $response = $this->get('https://localhost/login');

        $response->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertFalse(config('app.debug'));
    }

    public function test_production_rejects_plain_http(): void
    {
        $this->app->instance('env', 'production');

        $this->get('/')->assertBadRequest();
    }

    public function test_untrusted_forwarded_headers_cannot_bypass_https(): void
    {
        $this->app->instance('env', 'production');

        $this->withHeader('X-Forwarded-Proto', 'https')->get('/')->assertBadRequest();
    }

    public function test_configured_proxy_can_forward_https_and_receives_hsts(): void
    {
        $this->withoutVite();
        $this->app->instance('env', 'production');
        TrustProxies::at(['127.0.0.1']);

        $this->withHeader('X-Forwarded-Proto', 'https')->get('/')
            ->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_public_pages_have_compatible_headers_and_allow_local_http(): void
    {
        $this->withoutVite();

        $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_csrf_remains_enabled_for_state_changing_web_requests(): void
    {
        Route::post('/csrf-probe', fn () => response('accepted'))->middleware('web');
        $this->app->instance('env', 'local');

        $this->post('/csrf-probe')->assertStatus(419);
        $this->withSession(['_token' => 'csrf-test-token'])
            ->post('/csrf-probe', ['_token' => 'csrf-test-token'])->assertSee('accepted');
    }
}
