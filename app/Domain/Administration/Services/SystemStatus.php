<?php

namespace App\Domain\Administration\Services;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemStatus
{
    public function snapshot(Migrator $migrator): array
    {
        try {
            DB::connection()->select('select 1');
            $database = 'ok';
        } catch (Throwable $e) {
            report($e);
            $database = 'unavailable';
        }

        $migrations = ['status' => 'unavailable', 'applied' => null, 'pending' => null];
        try {
            if ($migrator->repositoryExists()) {
                $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
                $ran = $migrator->getRepository()->getRan();
                $migrations = [
                    'status' => 'available',
                    'applied' => count($ran),
                    'pending' => array_values(array_diff(array_keys($files), $ran)),
                ];
            } else {
                $migrations['status'] = 'repository_missing';
            }
        } catch (Throwable $e) {
            report($e);
        }

        return [
            'database' => $database,
            'storage_writable' => is_writable(storage_path()),
            'migrations' => $migrations,
        ];
    }
}
