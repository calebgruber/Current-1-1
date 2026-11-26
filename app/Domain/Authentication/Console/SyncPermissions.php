<?php

namespace App\Domain\Authentication\Console;

use App\Domain\Authentication\Services\PermissionRegistry;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'stagetrack:sync-permissions {--prune : Remove permissions no longer in the catalog}';

    protected $description = 'Synchronise the permission catalog (config/permissions.php) into the database';

    public function handle(PermissionRegistry $registry): int
    {
        $result = $registry->sync((bool) $this->option('prune'));
        $this->info("Permissions synced: {$result['created']} created, {$result['updated']} updated, {$result['removed']} removed.");

        return self::SUCCESS;
    }
}
