<?php

use App\Domain\Availability\Http\Controllers\AvailabilityController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability.index');
    Route::get('/availability/search', [AvailabilityController::class, 'search'])->middleware('throttle:240,1')->name('availability.search');
    Route::get('/availability/products/{product}', [AvailabilityController::class, 'product'])->whereNumber('product')->middleware('throttle:240,1')->name('availability.product');
});
