<?php

declare(strict_types=1);

use App\Core\Blueprint;
use App\Core\Database;
use App\Core\Schema;

return [
    'up' => function (Schema $schema, Database $db): void {
        $schema->create('contacts', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('organization', 120)->nullable();
            $t->string('role', 120)->nullable();
            $t->string('email', 190)->nullable();
            $t->string('phone', 60)->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index('name');
        });

        $schema->create('shows', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 20);
            $t->string('title', 190);
            $t->string('status', 20)->default('draft');
            $t->string('venue', 190)->nullable();
            $t->string('department', 60)->nullable();
            $t->datetime('prep_at')->nullable();
            $t->datetime('out_at');
            $t->date('opens_on')->nullable();
            $t->date('closes_on')->nullable();
            $t->datetime('return_at');
            $t->text('notes')->nullable();
            $t->string('share_token', 64)->nullable();
            $t->foreignId('created_by', 'users', 'SET NULL')->nullable();
            $t->timestamps();
            $t->unique('reference');
            $t->unique('share_token');
            $t->index('out_at');
        });

        $schema->create('show_contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('show_id', 'shows', 'CASCADE');
            $t->foreignId('contact_id', 'contacts', 'CASCADE');
            $t->string('role', 120)->nullable();
            $t->unique('show_id', 'contact_id');
        });

        // The pull list: what the show needs. Accessory lines point at their parent line.
        $schema->create('show_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('show_id', 'shows', 'CASCADE');
            $t->foreignId('item_id', 'items', 'RESTRICT');
            $t->integer('quantity')->default(1);
            $t->foreignId('parent_id', 'show_items', 'CASCADE')->nullable();
            $t->string('section', 60)->nullable();
            $t->text('notes')->nullable();
            $t->integer('sort')->default(0);
            $t->timestamps();
        });

        // Containers (road cases) lent to the show. Some come straight back after transport.
        $schema->create('show_containers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('show_id', 'shows', 'CASCADE');
            $t->foreignId('container_id', 'containers', 'RESTRICT');
            $t->boolean('return_after_transport')->default(false);
            $t->string('status', 20)->default('prepped');
            $t->datetime('returned_at')->nullable();
            $t->timestamps();
            $t->unique('show_id', 'container_id');
        });

        // What was actually pulled, packed (optionally into a container), sent out and returned.
        $schema->create('checkouts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('show_id', 'shows', 'CASCADE');
            $t->foreignId('item_id', 'items', 'RESTRICT');
            $t->foreignId('asset_id', 'assets', 'SET NULL')->nullable();
            $t->foreignId('container_id', 'containers', 'SET NULL')->nullable();
            $t->integer('quantity')->default(1);
            $t->string('status', 20)->default('prepped');
            $t->integer('returned_quantity')->default(0);
            $t->integer('damaged_quantity')->default(0);
            $t->text('notes')->nullable();
            $t->foreignId('prepped_by', 'users', 'SET NULL')->nullable();
            $t->foreignId('returned_by', 'users', 'SET NULL')->nullable();
            $t->datetime('prepped_at')->nullable();
            $t->datetime('out_at')->nullable();
            $t->datetime('returned_at')->nullable();
            $t->timestamps();
            $t->index('status');
        });
    },
    'down' => function (Schema $schema): void {
        foreach (['checkouts', 'show_containers', 'show_items', 'show_contacts', 'shows', 'contacts'] as $table) {
            $schema->drop($table);
        }
    },
];
