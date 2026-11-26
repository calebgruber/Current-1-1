<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64)->index(); // product, asset, job, reservation, document, contact, maintenance, location
            $table->string('key', 64);
            $table->string('label');
            $table->string('type', 24); // see CustomFieldType enum
            $table->string('group')->nullable();
            $table->string('help_text')->nullable();
            $table->json('options')->nullable(); // dropdown choices, relation target, currency code...
            $table->json('validation')->nullable(); // min, max, regex...
            $table->boolean('is_required')->default(false);
            $table->boolean('show_in_list')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['entity_type', 'key']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('definition_id')->constrained('custom_field_definitions')->cascadeOnDelete();
            $table->morphs('entity');
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 18, 4)->nullable();
            $table->dateTime('value_date')->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps();
            $table->unique(['definition_id', 'entity_type', 'entity_id']);
            $table->index(['definition_id', 'value_number']);
        });

        Schema::create('validation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('severity', 16)->default('warning'); // info | warning | error
            $table->boolean('is_active')->default(true);
            $table->json('definition');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 32)->index(); // quotation, pull_sheet, invoice...
            $table->string('entity_type', 32)->default('job');
            $table->string('editor', 16)->default('visual'); // visual | code
            $table->json('blocks')->nullable();
            $table->longText('html')->nullable();
            $table->longText('css')->nullable();
            $table->string('paper_size', 16)->default('letter');
            $table->string('orientation', 16)->default('portrait');
            $table->string('watermark')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->string('type', 32);
            $table->morphs('documentable');
            $table->string('title');
            $table->longText('html');
            $table->string('pdf_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('source', 32); // ReportSourceRegistry key
            $table->json('definition'); // columns, filters, group_by, sort, aggregates, chart
            $table->boolean('is_shared')->default(true);
            $table->json('role_ids')->nullable(); // restrict visibility to roles (null = everyone with reports.view)
            $table->boolean('show_on_dashboard')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('frequency', 16); // daily | weekly | monthly
            $table->string('format', 8)->default('csv'); // csv | xlsx | pdf
            $table->json('recipients');
            $table->unsignedTinyInteger('hour')->default(7);
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('search_index', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('entity_id');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('keywords')->nullable();
            $table->string('codes', 512)->nullable(); // barcodes, serials, asset numbers (exact match)
            $table->string('url');
            $table->timestamps();
            $table->unique(['entity_type', 'entity_id']);
            $table->index('title');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::getConnection()->statement('ALTER TABLE search_index ADD FULLTEXT search_index_fulltext (title, subtitle, keywords, codes)');
        }
    }

    public function down(): void
    {
        foreach (['search_index', 'report_schedules', 'reports', 'generated_documents', 'document_templates', 'validation_rules', 'custom_field_values', 'custom_field_definitions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
