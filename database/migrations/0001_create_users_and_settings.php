<?php

declare(strict_types=1);

use App\Core\Blueprint;
use App\Core\Database;
use App\Core\Schema;

return [
    'up' => function (Schema $schema, Database $db): void {
        $schema->create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('email', 190);
            $t->string('password_hash');
            $t->string('role', 20)->default('student');
            $t->boolean('active')->default(true);
            $t->string('theme', 10)->default('auto');
            $t->boolean('sound_muted')->default(false);
            $t->datetime('last_login_at')->nullable();
            $t->timestamps();
            $t->unique('email');
        });

        $schema->create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->text('value')->nullable();
            $t->unique('name');
        });
    },
    'down' => function (Schema $schema): void {
        $schema->drop('settings');
        $schema->drop('users');
    },
];
