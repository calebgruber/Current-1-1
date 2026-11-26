<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Base data required by every installation. Safe to run repeatedly.
 * Demo data is opt-in: php artisan db:seed --class=DemoDataSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            DocumentTemplateSeeder::class,
            ValidationRuleSeeder::class,
        ]);
    }
}
