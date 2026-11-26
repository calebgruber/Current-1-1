<?php

use App\Domain\Inventory\Http\Controllers\CategoryController;
use App\Domain\Inventory\Http\Controllers\LocationController;
use App\Domain\Inventory\Http\Controllers\ManufacturerController;
use App\Domain\Inventory\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/products/lookup', [ProductController::class, 'lookup'])->middleware('throttle:120,1')->name('products.lookup');
    Route::get('/products/labels', [ProductController::class, 'labels'])->name('products.labels');
    Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');
    Route::put('/products/{product}/stock', [ProductController::class, 'updateStock'])->name('products.stock.update');
    Route::delete('/products/{product}/stock/{stockLevel}', [ProductController::class, 'destroyStock'])->name('products.stock.destroy');
    Route::post('/products/{product}/images', [ProductController::class, 'storeImage'])->name('products.images.store');
    Route::delete('/products/{product}/images/{image}', [ProductController::class, 'destroyImage'])->name('products.images.destroy');
    Route::post('/products/{product}/images/{image}/primary', [ProductController::class, 'primaryImage'])->name('products.images.primary');
    Route::resource('products', ProductController::class)->whereNumber('product');

    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy'])->whereNumber('category');
    Route::resource('manufacturers', ManufacturerController::class)->only(['index', 'store', 'update', 'destroy'])->whereNumber('manufacturer');

    Route::get('/locations/lookup', [LocationController::class, 'lookup'])->middleware('throttle:120,1')->name('locations.lookup');
    Route::resource('locations', LocationController::class)->whereNumber('location');
});
