<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_events', function (Blueprint $table) {
            $table->id();
            $table->string('mode', 16)->index(); // pick, prep, stage, checkout, return, damage, lookup
            $table->string('code', 191);
            $table->string('result', 16); // success | warning | error
            $table->string('message');
            $table->foreignId('job_id')->nullable()->constrained('production_jobs')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('station', 64)->nullable(); // display / scanner station identifier
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_events');
    }
};
