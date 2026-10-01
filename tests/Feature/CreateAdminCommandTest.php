<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_a_super_admin_with_a_hashed_password_and_requires_enrollment(): void
    {
        $this->artisan('admin:create operator@example.com --super-admin')
            ->expectsQuestion('Name', 'Parish operator')
            ->expectsQuestion('Password (at least 12 characters)', 'A-long-passphrase-48!')
            ->expectsQuestion('Confirm password', 'A-long-passphrase-48!')
            ->expectsOutput('Administrator created. Complete authenticator enrollment at the first login.')
            ->assertSuccessful();

        $user = User::where('email', 'operator@example.com')->sole();
        $this->assertSame(User::ROLE_SUPER_ADMIN, $user->role);
        $this->assertTrue(Hash::check('A-long-passphrase-48!', $user->password));
        $this->assertNotSame('A-long-passphrase-48!', $user->password);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_refuses_to_overwrite_an_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'operator@example.com']);

        $this->artisan('admin:create operator@example.com --super-admin')
            ->expectsQuestion('Name', 'Parish operator')
            ->expectsQuestion('Password (at least 12 characters)', 'A-long-passphrase-48!')
            ->expectsQuestion('Confirm password', 'A-long-passphrase-48!')
            ->assertFailed();

        $this->assertSame('user', $user->refresh()->role);
    }

    public function test_passwords_cannot_be_supplied_non_interactively(): void
    {
        $this->artisan('admin:create operator@example.com --no-interaction')->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
