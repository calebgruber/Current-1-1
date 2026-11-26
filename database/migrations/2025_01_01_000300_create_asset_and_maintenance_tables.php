<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('asset_number', 64)->unique();
            $table->string('serial_number', 128)->nullable()->index();
            $table->string('barcode', 128)->nullable()->unique();
            $table->string('status', 24)->default('available')->index(); // see AssetStatus enum
            $table->string('condition', 24)->default('good'); // new, good, fair, poor
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('purchased_at')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->date('warranty_expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['product_id', 'status']);
        });

        Schema::create('asset_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('context'); // job, maintenance record, scan...
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('condition_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('condition', 24);
            $table->text('notes')->nullable();
            $table->json('photos')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1); // for bulk stock
            $table->string('type', 24)->default('repair'); // repair, inspection, pat_test, cleaning, damage, calibration
            $table->string('status', 24)->default('open')->index(); // open, in_progress, awaiting_parts, completed, written_off
            $table->string('priority', 16)->default('normal');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('resolution')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->foreignId('job_id')->nullable(); // FK added after jobs table exists
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['maintenance_records', 'condition_reports', 'asset_status_history', 'assets'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
