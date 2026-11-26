<?php

namespace Tests\Feature\Setup;

use App\Domain\Setup\Services\Installer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InstallerSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function administratorData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Administrator',
            'username' => 'new-admin',
            'email' => 'admin@example.com',
            'password' => 'Password12345',
        ], $overrides);
    }

    public function test_fresh_install_creates_one_administrator(): void
    {
        $installer = app(Installer::class);
        $admin = $installer->createAdministrator($this->administratorData());

        $this->assertTrue($admin->is_super_admin);
        $this->assertTrue(Hash::check('Password12345', $admin->password));
        $this->expectException(ValidationException::class);
        $installer->createAdministrator($this->administratorData(['email' => 'another@example.com']));
    }

    public function test_existing_account_is_not_overwritten_or_promoted(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com', 'is_super_admin' => false]);
        $before = $user->getRawOriginal('password');
        try {
            app(Installer::class)->createAdministrator($this->administratorData());
            $this->fail('Existing account must not be reused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }

        $user->refresh();
        $this->assertFalse($user->is_super_admin);
        $this->assertSame($before, $user->getRawOriginal('password'));
    }

    public function test_soft_deleted_accounts_and_existing_administrators_are_not_reused(): void
    {
        $user = User::factory()->create(['username' => 'new-admin', 'is_super_admin' => false]);
        $user->delete();
        try {
            app(Installer::class)->createAdministrator($this->administratorData());
            $this->fail('Deleted username must not be reused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('username', $e->errors());
        }

        $user->forceFill(['is_super_admin' => true])->save();
        $this->assertTrue(app(Installer::class)->hasAdministrator());
        $this->expectException(ValidationException::class);
        app(Installer::class)->createAdministrator($this->administratorData(['username' => 'different']));
    }
}
