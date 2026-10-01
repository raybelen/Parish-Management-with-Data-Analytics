<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_cannot_manage_roles_even_with_valid_mfa(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $target = User::factory()->create();

        $this->verified($admin)->get('/admin/roles')->assertForbidden();
        $this->patch('/admin/roles/'.$target->id, ['role' => 'super_admin', 'password' => 'password'])->assertForbidden();

        $this->assertSame('user', $target->refresh()->role);
    }

    public function test_super_admin_can_change_roles_and_sessions_are_revoked_and_audited(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->withTwoFactor()->create();
        $target = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'target-session', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);
        $handler = new TestHandler;
        Log::channel('security')->getLogger()->pushHandler($handler);
        $this->verified($admin)->get('/admin/roles')->assertSee($target->email);

        $this->patch('/admin/roles/'.$target->id, ['role' => 'admin', 'password' => 'password'])->assertRedirect();

        $this->assertSame('admin', $target->refresh()->role);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertTrue($handler->hasInfo('user.role_changed'));
        $record = collect($handler->getRecords())->first(fn ($record) => $record->message === 'user.role_changed');
        $this->assertSame('user', $record->context['previous_role']);
    }

    public function test_super_admin_cannot_change_own_role(): void
    {
        $admin = User::factory()->superAdmin()->withTwoFactor()->create();

        $this->verified($admin)->patch('/admin/roles/'.$admin->id, ['role' => 'user', 'password' => 'password'])->assertForbidden();

        $this->assertSame('super_admin', $admin->refresh()->role);
    }

    public function test_role_changes_require_valid_role_and_current_password(): void
    {
        $admin = User::factory()->superAdmin()->withTwoFactor()->create();
        $target = User::factory()->create();

        $this->verified($admin)->patch('/admin/roles/'.$target->id, ['role' => 'owner', 'password' => 'wrong'])
            ->assertSessionHasErrors(['role', 'password']);

        $this->assertSame('user', $target->refresh()->role);
    }

    public function test_revoked_role_cannot_access_admin_with_previous_mfa_session(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $this->verified($admin);
        $admin->role = 'user';
        $admin->save();

        $this->get('/admin')->assertForbidden();
    }

    public function test_role_promotion_requires_a_fresh_login_even_with_a_non_database_session(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $this->verified($admin);
        $admin->role = 'super_admin';
        $admin->save();

        $this->get('/admin/roles')->assertRedirect('/login');
        $this->assertGuest();
    }

    #[DataProvider('roles')]
    public function test_role_permissions_are_explicit(string $role, bool $access, bool $manage): void
    {
        $user = User::factory()->make(['role' => $role]);

        $this->assertSame($access, Gate::forUser($user)->allows('access-admin'));
        $this->assertSame($manage, Gate::forUser($user)->allows('manage-admin-roles'));
    }

    /** @return array<string, array{string, bool, bool}> */
    public static function roles(): array
    {
        return [
            'ordinary user' => ['user', false, false],
            'administrator' => ['admin', true, false],
            'super administrator' => ['super_admin', true, true],
            'unknown role fails closed' => ['owner', false, false],
        ];
    }

    public function test_role_and_mfa_attributes_cannot_be_mass_assigned(): void
    {
        $user = new User;
        $user->fill(['name' => 'User', 'role' => 'super_admin', 'two_factor_secret' => 'secret', 'two_factor_confirmed_at' => now()]);

        $this->assertNull($user->role);
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_credentials_are_excluded_from_user_serialization(): void
    {
        $user = User::factory()->make(['two_factor_secret' => 'private', 'two_factor_recovery_codes' => 'private']);

        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
        $this->assertArrayNotHasKey('two_factor_secret', $user->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $user->toArray());
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession([
            'admin.mfa_user_id' => $user->id,
            'admin.mfa_fingerprint' => $user->adminSessionFingerprint(),
        ]);
    }
}
