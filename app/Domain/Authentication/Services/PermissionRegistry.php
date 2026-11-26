<?php

namespace App\Domain\Authentication\Services;

use App\Domain\Authentication\Models\Permission;
use Illuminate\Support\Facades\DB;

/**
 * Flattens config/permissions.php into permission keys and synchronises the
 * permissions table. Roles are never defined here.
 */
final class PermissionRegistry
{
    public const ACTION_LABELS = [
        'view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete',
        'export' => 'Export', 'approve' => 'Approve', 'admin' => 'Administer',
    ];

    public function __construct(private readonly ?array $catalog = null) {}

    /** @return list<array{key: string, module: string, resource: string, action: string, label: string}> */
    public function flatten(): array
    {
        $out = [];
        foreach ($this->catalog ?? config('permissions', []) as $module => $m) {
            foreach ($m['resources'] as $resource => $r) {
                foreach ($r['actions'] as $action) {
                    $out[] = [
                        'key' => "{$module}.{$resource}.{$action}",
                        'module' => $module,
                        'resource' => $resource,
                        'action' => $action,
                        'label' => (self::ACTION_LABELS[$action] ?? ucfirst($action)).' '.mb_strtolower($r['label']),
                    ];
                }
            }
        }

        return $out;
    }

    /** Grouped catalogue for the role editor matrix. */
    public function matrix(): array
    {
        $out = [];
        foreach ($this->catalog ?? config('permissions', []) as $module => $m) {
            $resources = [];
            foreach ($m['resources'] as $resource => $r) {
                $resources[] = [
                    'key' => $resource,
                    'label' => $r['label'],
                    'actions' => array_map(fn ($a) => ['action' => $a, 'key' => "{$module}.{$resource}.{$a}"], $r['actions']),
                ];
            }
            $out[] = ['key' => $module, 'label' => $m['label'], 'resources' => $resources];
        }

        return $out;
    }

    /** @return array{created: int, updated: int, removed: int} */
    public function sync(bool $prune = false): array
    {
        $created = $updated = $removed = 0;
        $keys = [];
        DB::transaction(function () use (&$created, &$updated, &$removed, &$keys, $prune) {
            foreach ($this->flatten() as $p) {
                $keys[] = $p['key'];
                $model = Permission::query()->firstOrNew(['key' => $p['key']]);
                $model->fill($p);
                if (! $model->exists) {
                    $created++;
                } elseif ($model->isDirty()) {
                    $updated++;
                }
                $model->save();
            }
            if ($prune) {
                $stale = Permission::query()->whereNotIn('key', $keys)->get();
                foreach ($stale as $permission) {
                    $permission->roles()->detach();
                    $permission->delete();
                    $removed++;
                }
            }
        });

        return compact('created', 'updated', 'removed');
    }

    public static function actionLabels(): array
    {
        return self::ACTION_LABELS;
    }
}
