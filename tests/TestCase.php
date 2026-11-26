<?php

namespace Tests;

use App\Domain\Authentication\Models\Permission;
use App\Domain\Authentication\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use InvalidArgumentException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pages live in resources/js/Modules/{Module}/Pages/{Page}.vue ('Inventory/Products/Index').
        $this->app->bind('inertia.testing.view-finder', fn () => new class
        {
            public function find(string $name): string
            {
                [$module, $rest] = array_pad(explode('/', $name, 2), 2, 'Index');
                $path = resource_path("js/Modules/{$module}/Pages/{$rest}.vue");
                if (! is_file($path)) {
                    throw new InvalidArgumentException("Page [{$name}] not found at {$path}.");
                }

                return $path;
            }
        });
    }

    protected function superAdmin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        // Super admins must have 2FA by default policy; enrol a dummy secret so tests reach the page.
        $user->forceFill([
            'is_super_admin' => true,
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * Create a user holding exactly the given permission keys through a fresh role.
     *
     * @param  list<string>  $permissions
     */
    protected function userWithPermissions(array $permissions, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $role = Role::create(['name' => 'Test role '.uniqid()]);
        $ids = collect($permissions)->map(function (string $key) {
            [$module, $resource, $action] = array_pad(explode('.', $key, 3), 3, '');

            return Permission::firstOrCreate(['key' => $key], [
                'module' => $module, 'resource' => $resource, 'action' => $action, 'label' => $key,
            ])->id;
        });
        $role->permissions()->sync($ids);
        $user->roles()->attach($role);

        return $user;
    }
}
