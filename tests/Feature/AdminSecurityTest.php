<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_enrollment_requires_password_and_valid_totp_before_admin_access(): void
    {
        $user = User::factory()->admin()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/admin/security/two-factor', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertNull($user->refresh()->two_factor_secret);

        $this->post('/admin/security/two-factor', ['password' => 'password'])->assertRedirect('/admin/security');
        $user->refresh();
        $this->assertNotSame(decrypt($user->two_factor_secret), $user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->get('/admin')->assertRedirect('/admin/security');
        $this->get('/admin/security')->assertSee('Manual setup key:');
        $this->post('/admin/security/two-factor/confirm', ['code' => 'invalid'])->assertSessionHasErrors('code');

        $this->post('/admin/security/two-factor/confirm', ['code' => (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret))])
            ->assertRedirect('/admin/security');
        $this->assertNotNull($user->refresh()->two_factor_confirmed_at);
        $codes = $user->recoveryCodes();
        $this->get('/admin/security')->assertSee($codes[0])->assertDontSee('Manual setup key:');
        $this->get('/admin/security')->assertDontSee($codes[0]);
        $this->get('/admin')->assertSee('Welcome, '.$user->name);
    }

    public function test_invalid_totp_does_not_confirm_enrollment(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create(['two_factor_confirmed_at' => null]);
        $this->actingAs($user)->post('/admin/security/two-factor/confirm', ['code' => '000000'])
            ->assertSessionHasErrors('code', errorBag: 'confirmTwoFactorAuthentication');

        $this->assertNull($user->refresh()->two_factor_confirmed_at);
        $this->get('/admin')->assertRedirect('/admin/security');
    }

    public function test_existing_authenticator_cannot_be_overwritten_via_enrollment(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $originalSecret = $user->two_factor_secret;

        $this->verified($user)->post('/admin/security/two-factor', ['password' => 'password'])->assertConflict();

        $this->assertSame($originalSecret, $user->refresh()->two_factor_secret);
    }

    public function test_recovery_code_regeneration_requires_password_and_replaces_previous_codes(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->verified($user)->post('/admin/security/recovery-codes', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertContains('recovery-code-one', $user->refresh()->recoveryCodes());

        $this->post('/admin/security/recovery-codes', ['password' => 'password'])->assertRedirect('/admin/security');

        $this->assertNotContains('recovery-code-one', $user->refresh()->recoveryCodes());
        $this->assertCount(8, $user->recoveryCodes());
        $this->get('/admin/security')->assertSee($user->recoveryCodes()[0]);
    }

    public function test_password_change_hashes_new_password_and_revokes_all_sessions(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->verified($user)->put('/admin/security/password', [
            'current_password' => 'password',
            'password' => 'New-parish-password-54!',
            'password_confirmation' => 'New-parish-password-54!',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('New-parish-password-54!', $user->refresh()->password));
        $this->assertGuest();
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
    }

    public function test_password_change_rejects_incorrect_current_password_and_weak_password(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $originalHash = $user->password;

        $this->verified($user)->put('/admin/security/password', [
            'current_password' => 'wrong',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertSessionHasErrors(['current_password', 'password']);

        $this->assertSame($originalHash, $user->refresh()->password);
    }

    public function test_changed_authenticator_invalidates_a_previously_verified_session(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->verified($user);
        $user->two_factor_secret = encrypt('ANOTHERSECRETKEY');
        $user->save();

        $this->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_password_change_revokes_verified_sessions_before_first_admin_page_visit(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->verified($user);
        $user->password = 'A-new-parish-password-54!';
        $user->save();

        $this->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_security_events_exclude_credentials(): void
    {
        $handler = new TestHandler;
        Log::channel('security')->getLogger()->pushHandler($handler);
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect-password']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-one']);
        $this->post('/logout');

        $this->assertTrue($handler->hasInfo('auth.login_failed'));
        $this->assertTrue($handler->hasInfo('admin.login'));
        $this->assertTrue($handler->hasInfo('admin.logout'));
        $this->assertTrue($handler->hasInfo('admin.recovery_code_used'));
        foreach ($handler->getRecords() as $record) {
            $this->assertSame(['actor_id', 'subject_id', 'role', 'previous_role', 'ip'], array_keys($record->context));
        }
        $serialized = json_encode($handler->getRecords());
        $this->assertStringNotContainsString('incorrect-password', $serialized);
        $this->assertStringNotContainsString('recovery-code-one', $serialized);
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $serialized);
        $this->assertStringNotContainsString($user->password, $serialized);
    }

    public function test_login_mfa_and_admin_writes_require_csrf_tokens(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->app->instance('env', 'local');

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(419);
        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-one'])->assertStatus(419);
        $this->verified($user)->post('/admin/security/recovery-codes', ['password' => 'password'])->assertStatus(419);
        $this->post('/logout')->assertStatus(419);
        $this->assertContains('recovery-code-one', $user->refresh()->recoveryCodes());
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession([
            'admin.mfa_user_id' => $user->id,
            'admin.mfa_fingerprint' => $user->adminSessionFingerprint(),
        ]);
    }
}
