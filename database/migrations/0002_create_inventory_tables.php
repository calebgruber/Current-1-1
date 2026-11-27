<?php

declare(strict_types=1);

use App\Core\Blueprint;
use App\Core\Database;
use App\Core\Schema;

return [
    'up' => function (Schema $schema, Database $db): void {
        $schema->create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('department', 30)->default('lighting');
            $t->integer('sort')->default(0);
            $t->timestamps();
        });

        // Places inside the one shop: shelves, cages, bins, carts.
        $schema->create('locations', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('code', 64)->nullable();
            $t->text('description')->nullable();
            $t->integer('sort')->default(0);
            $t->timestamps();
            $t->unique('code');
        });

        // Road cases, tubs, cable trunks and bags that equipment is packed into.
        $schema->create('containers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('barcode', 64)->nullable();
            $t->string('kind', 30)->default('roadcase');
            $t->foreignId('location_id', 'locations', 'SET NULL')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique('barcode');
        });

        $schema->create('items', function (Blueprint $t) {
            $t->id();
            $t->string('name', 190);
            $t->foreignId('category_id', 'categories', 'SET NULL')->nullable();
            $t->string('tracking', 10)->default('bulk');
            $t->string('barcode', 64)->nullable();
            $t->integer('quantity')->default(0);
            $t->foreignId('location_id', 'locations', 'SET NULL')->nullable();
            $t->string('manufacturer', 120)->nullable();
            $t->string('model', 120)->nullable();
            $t->text('description')->nullable();
            $t->text('notes')->nullable();
            $t->decimal('replacement_cost')->nullable();
            $t->string('photo', 120)->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique('barcode');
            $t->index('name');
        });

        // Individually tracked units of a serial item.
        $schema->create('assets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_id', 'items', 'CASCADE');
            $t->string('asset_tag', 64);
            $t->string('serial_number', 120)->nullable();
            $t->foreignId('location_id', 'locations', 'SET NULL')->nullable();
            $t->string('status', 20)->default('available');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique('asset_tag');
        });

        // Accessories that always travel with an item (a light gets a gel frame and a safety cable).
        $schema->create('item_accessories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_id', 'items', 'CASCADE');
            $t->foreignId('accessory_id', 'items', 'CASCADE');
            $t->integer('quantity')->default(1);
            $t->timestamps();
            $t->unique('item_id', 'accessory_id');
        });
    },
    'down' => function (Schema $schema): void {
        foreach (['item_accessories', 'assets', 'items', 'containers', 'locations', 'categories'] as $table) {
            $schema->drop($table);
        }
    },
];
