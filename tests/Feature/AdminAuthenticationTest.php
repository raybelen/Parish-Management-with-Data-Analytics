<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_login_and_non_admins_receive_403(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_login_and_challenge_pages_render(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();

        $this->get('/login')->assertSee('Administrator sign in');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->get('/two-factor-challenge')->assertSee('Verify your sign in');
    }

    public function test_password_login_regenerates_session_but_requires_mfa_before_admin_access(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->withSession(['_token' => 'before-login']);
        $previousId = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/two-factor-challenge');
        $this->assertNotSame($previousId, session()->getId());
        $this->assertGuest();
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_totp_login_regenerates_session_and_grants_admin_access(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $previousId = session()->getId();

        $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret))])
            ->assertRedirect('/admin');
        $this->assertNotSame($previousId, session()->getId());
        $this->assertAuthenticatedAs($user);
        $this->get('/admin')->assertSee('Welcome, '.$user->name);
    }

    public function test_recovery_codes_are_single_use_and_do_not_create_remember_cookies(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true]);

        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-one'])
            ->assertRedirect('/admin')->assertCookieMissing(Auth::guard('web')->getRecallerName());
        $this->assertNotContains('recovery-code-one', $user->refresh()->recoveryCodes());
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-one'])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_totp_codes_cannot_be_replayed(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $code = (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret));
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect('/admin');
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_logout_invalidates_session_and_csrf_token(): void
    {
        $user = User::factory()->admin()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        session()->put('private-data', 'must-disappear');
        $previousId = session()->getId();
        $previousToken = session()->token();

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->assertNotSame($previousId, session()->getId());
        $this->assertNotSame($previousToken, session()->token());
        $this->assertFalse(session()->has('private-data'));
    }

    public function test_admin_without_mfa_can_only_reach_enrollment(): void
    {
        $user = User::factory()->admin()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect('/admin/security');
        $this->get('/admin/security')->assertSee('Set up your authenticator');
        $this->get('/admin/roles')->assertRedirect('/admin/security');
    }

    public function test_alternate_password_only_session_cannot_bypass_mfa_or_view_secrets(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();

        $this->actingAs($user)->get('/admin/security')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_invalid_password_and_non_admin_login_return_generic_errors(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);
        $this->assertGuest();
    }

    public function test_login_rate_limit_applies_to_normalized_identifier_and_expires(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'ADMIN@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong'])->assertTooManyRequests();
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
    }

    public function test_malformed_login_identifier_returns_validation_errors(): void
    {
        $this->post('/login', ['email' => ['unexpected'], 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_account_rate_limit_applies_across_multiple_ips(): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($attempt + 1)])
                ->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.100'])
            ->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_login_ip_limit_prevents_rotating_identifiers(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->post('/login', ['email' => "user{$attempt}@example.com", 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'another@example.com', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_mfa_attempts_are_limited(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/two-factor-challenge', ['code' => 'invalid'])->assertSessionHasErrors('code');
        }

        $this->post('/two-factor-challenge', ['code' => 'invalid'])->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_expired_mfa_challenge_requires_password_again(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->travel(6)->minutes();

        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-one'])->assertRedirect('/login');
        $this->assertGuest();
        $this->assertContains('recovery-code-one', $user->refresh()->recoveryCodes());
    }

    public function test_password_change_invalidates_pending_mfa_challenge(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $user->password = 'A-new-password-54!';
        $user->save();

        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-one'])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_alternate_authentication_and_mfa_disable_routes_are_not_exposed(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
        $this->post('/passkeys/login')->assertNotFound();
        $this->delete('/user/two-factor-authentication')->assertNotFound();
        $this->post('/user/two-factor-authentication')->assertNotFound();
    }
}
