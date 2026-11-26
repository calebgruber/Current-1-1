<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('name');
            $table->string('code', 64)->nullable()->unique();
            $table->string('type', 32)->default('warehouse'); // warehouse, room, shelf, bin, venue, vehicle
            $table->string('barcode', 128)->nullable()->unique();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 16)->nullable();
            $table->string('icon', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('website')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku', 64)->nullable()->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model')->nullable();
            $table->text('description')->nullable();
            $table->string('tracking', 16)->default('serialized'); // serialized | bulk
            $table->decimal('replacement_cost', 12, 2)->nullable();
            $table->decimal('rental_price', 12, 2)->nullable();
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->string('barcode', 128)->nullable()->unique();
            $table->string('image_path')->nullable();
            $table->foreignId('default_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['category_id', 'is_active']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_accessories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accessory_product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2)->default(1); // per parent unit
            $table->string('type', 16)->default('required'); // required | optional | safety | consumable
            $table->boolean('auto_add')->default(true);
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'accessory_product_id']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('color', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->primary(['tag_id', 'taggable_type', 'taggable_id']);
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('quantity_maintenance')->default(0);
            $table->unsignedInteger('quantity_damaged')->default(0);
            $table->unsignedInteger('quantity_lost')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'location_id']);
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->default('person'); // person | organization
            $table->foreignId('organization_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 64)->nullable();
            $table->string('title')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        foreach (['contacts', 'stock_levels', 'taggables', 'tags', 'product_accessories', 'product_images', 'products', 'manufacturers', 'categories', 'locations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
