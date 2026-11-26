<?php

namespace Database\Seeders;

use App\Domain\Authentication\Models\Permission;
use App\Domain\Authentication\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Starter roles. These are ordinary database rows: administrators can edit,
 * rename or delete them and create unlimited additional roles. Existing roles
 * are never modified, so re-running the seeder will not undo customisations.
 */
class RoleSeeder extends Seeder
{
    /** @return array<string, array{description: string, color: string, default?: bool, permissions: list<string>}> */
    public static function definitions(): array
    {
        return [
            'Production Manager' => [
                'description' => 'Plans productions, approves jobs and manages inventory, documents and reports.',
                'color' => '#6366f1',
                'permissions' => ['inventory.*', 'assets.*', 'contacts.*', 'jobs.*', 'reservations.*', 'warehouse.*', 'maintenance.*', 'documents.*', 'reporting.*', 'rules.*.view', 'search.*'],
            ],
            'Warehouse Lead' => [
                'description' => 'Runs prep, checkout and returns; manages asset status and maintenance.',
                'color' => '#f59e0b',
                'permissions' => ['inventory.*.view', 'inventory.availability.*', 'assets.*.view', 'assets.status.edit', 'assets.labels.*', 'jobs.jobs.view', 'jobs.items.view', 'jobs.stages.edit', 'reservations.*.view', 'warehouse.*', 'maintenance.*', 'documents.documents.view', 'documents.documents.create', 'search.*'],
            ],
            'Student Crew' => [
                'description' => 'Scans equipment during picking, prep and returns.',
                'color' => '#10b981',
                'default' => true,
                'permissions' => ['inventory.products.view', 'inventory.availability.view', 'assets.assets.view', 'jobs.jobs.view', 'jobs.items.view', 'warehouse.*.view', 'warehouse.picking.edit', 'warehouse.prep.edit', 'warehouse.returns.edit', 'maintenance.records.create', 'search.*'],
            ],
            'Read Only' => [
                'description' => 'Can view operational data but not change it.',
                'color' => '#64748b',
                'permissions' => ['inventory.*.view', 'assets.*.view', 'contacts.*.view', 'jobs.*.view', 'reservations.*.view', 'warehouse.*.view', 'maintenance.*.view', 'documents.*.view', 'reporting.*.view', 'search.*'],
            ],
        ];
    }

    public function run(): void
    {
        $all = Permission::query()->pluck('id', 'key');

        foreach (self::definitions() as $name => $definition) {
            $role = Role::query()->firstOrNew(['name' => $name]);
            if ($role->exists) {
                continue;
            }
            $role->fill([
                'description' => $definition['description'],
                'color' => $definition['color'],
                'is_default' => $definition['default'] ?? false,
            ])->save();

            $ids = $all->filter(fn ($id, $key) => Str::is($definition['permissions'], $key))->values();
            $role->permissions()->sync($ids);
        }
    }
}
