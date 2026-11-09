<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ParishSetting;
use App\Models\Personnel;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeparationOfDutiesTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{string, bool}> */
    public static function roles(): array
    {
        return ['admin' => ['admin', false], 'staff' => ['staff', false], 'priest' => ['priest', false],
            'user' => ['user', false], 'unknown' => ['owner', false], 'super admin' => ['super_admin', true]];
    }

    #[DataProvider('roles')]
    public function test_privileged_abilities_cannot_be_granted_through_saved_permissions(string $role, bool $allowed): void
    {
        $abilities = ['settings.manage', 'audit.view', 'payments.approve', 'manage-admin-roles'];
        ParishSetting::query()->where('key', 'office')->update(['value' => ['permissions' => [$role => $abilities]]]);
        $user = User::factory()->create(['role' => $role]);

        foreach ($abilities as $ability) {
            $this->assertSame($allowed, $user->can($ability), $ability);
        }
    }

    public function test_admin_direct_requests_cannot_change_privileged_configuration_or_view_audits(): void
    {
        $this->withoutVite();
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $personnel = Personnel::factory()->create();
        $service = ServiceType::query()->firstOrFail();
        $this->verified($admin);

        foreach (['/settings', '/audit', '/users', '/roles', '/payment-adjustments'] as $path) {
            $this->get('/admin'.$path)->assertForbidden();
        }
        $this->put('/admin/settings', [])->assertForbidden();
        $this->put('/admin/service-types/'.$service->id, [])->assertForbidden();
        $this->post('/admin/service-types', [])->assertForbidden();
        $this->post('/admin/personnel', [])->assertForbidden();
        $this->put('/admin/personnel/'.$personnel->id, [])->assertForbidden();
        $this->delete('/admin/personnel/'.$personnel->id)->assertForbidden();
        $this->put('/admin/permissions', [])->assertForbidden();
        $this->post('/admin/backups', [])->assertForbidden();
        $this->post('/admin/users', [])->assertForbidden();
        $this->assertModelExists($personnel);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_keeps_operational_pages_and_has_no_privileged_navigation(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create();

        foreach (['/appointments', '/calendar', '/priests', '/records', '/reports', '/revenue', '/notifications', '/website-content'] as $path) {
            $this->get('/admin'.$path)->assertOk();
        }
        $this->get(route('admin.appointments.show', $appointment))
            ->assertSee('Request fee adjustment')->assertSee('Request refund entry')
            ->assertDontSee('Financial approvals')->assertDontSee('Parish settings')
            ->assertDontSee('User accounts')->assertDontSee('Audit trail');
    }

    public function test_super_admin_cannot_delegate_reserved_permissions(): void
    {
        $this->withoutVite();
        config(['office.permissions.admin' => ['appointments.view', 'settings.manage', 'audit.view', 'payments.approve']]);
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());
        $before = ParishSetting::query()->where('key', 'office')->value('value');

        $this->get('/admin/settings')->assertDontSee('value="settings.manage"', false)->assertDontSee('value="audit.view"', false)->assertDontSee('value="payments.approve"', false);
        $this->put('/admin/permissions', ['password' => 'password', 'permissions' => ['admin' => ['settings.manage', 'audit.view', 'payments.approve']]])
            ->assertSessionHasErrors(['permissions.admin.0', 'permissions.admin.1', 'permissions.admin.2']);
        $this->assertSame($before, ParishSetting::query()->where('key', 'office')->value('value'));
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }
}
