<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->string('name');
            $table->string('stage', 24)->default('inquiry')->index(); // JobStage enum
            $table->string('workflow_status', 64)->nullable(); // free-form secondary status
            $table->foreignId('client_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('venue_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prep_starts_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('returns_at')->nullable();
            $table->text('description')->nullable();
            $table->string('color', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['starts_at', 'returns_at']);
        });

        Schema::create('job_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('production_jobs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_item_id')->nullable()->constrained('job_items')->cascadeOnDelete(); // accessories
            $table->string('section')->nullable(); // e.g. "FOH Lighting", "Band Audio"
            $table->unsignedInteger('required_quantity')->default(0);
            $table->unsignedInteger('spare_quantity')->default(0);
            $table->unsignedInteger('picked_quantity')->default(0);
            $table->unsignedInteger('checked_out_quantity')->default(0);
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->string('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('job_item_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('active'); // active | spare
            $table->string('status', 24)->default('allocated'); // allocated, picked, prepped, staged, checked_out, returned, damaged, missing
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
            $table->unique(['job_item_id', 'asset_id']);
        });

        Schema::create('job_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('production_jobs')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->timestamps();
        });

        Schema::create('job_stage_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('production_jobs')->cascadeOnDelete();
            $table->string('from_stage', 24)->nullable();
            $table->string('to_stage', 24);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('production_jobs')->cascadeOnDelete();
            $table->foreignId('job_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name')->nullable(); // label for standalone holds
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('spare_quantity')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 16)->default('confirmed')->index(); // tentative | confirmed | released
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'starts_at', 'ends_at']);
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->morphs('notable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->foreign('job_id')->references('id')->on('production_jobs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropForeign(['job_id']);
        });
        foreach (['attachments', 'notes', 'reservations', 'job_stage_history', 'job_contacts', 'job_item_assets', 'job_items', 'production_jobs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
