<?php

declare(strict_types=1);

use App\Core\Blueprint;
use App\Core\Database;
use App\Core\Schema;

return [
    'up' => function (Schema $schema, Database $db): void {
        $schema->create('document_templates', function (Blueprint $t) {
            $t->id();
            $t->string('template_key', 60);
            $t->string('name', 120);
            $t->string('description')->nullable();
            $t->text('body');
            $t->foreignId('updated_by', 'users', 'SET NULL')->nullable();
            $t->timestamps();
            $t->unique('template_key');
        });

        // A generated document is a frozen snapshot, so it never changes after it was sent.
        $schema->create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('show_id', 'shows', 'CASCADE')->nullable();
            $t->string('template_key', 60);
            $t->string('title', 190);
            $t->text('body');
            $t->boolean('shared')->default(false);
            $t->foreignId('created_by', 'users', 'SET NULL')->nullable();
            $t->timestamps();
        });

        // The conversation between the shop and a show's production team.
        $schema->create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('show_id', 'shows', 'CASCADE');
            $t->foreignId('user_id', 'users', 'SET NULL')->nullable();
            $t->string('author_name', 120)->nullable();
            $t->string('direction', 20)->default('shop');
            $t->text('body');
            $t->foreignId('document_id', 'documents', 'SET NULL')->nullable();
            $t->datetime('created_at')->nullable();
        });

        $schema->create('activity', function (Blueprint $t) {
            $t->id();
            $t->foreignId('show_id', 'shows', 'CASCADE')->nullable();
            $t->foreignId('user_id', 'users', 'SET NULL')->nullable();
            $t->string('description');
            $t->datetime('created_at')->nullable();
        });
    },
    'down' => function (Schema $schema): void {
        foreach (['activity', 'messages', 'documents', 'document_templates'] as $table) {
            $schema->drop($table);
        }
    },
];
