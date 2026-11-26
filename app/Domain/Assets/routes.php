<?php

use App\Domain\Assets\Http\Controllers\AssetController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/assets/lookup', [AssetController::class, 'lookup'])->middleware('throttle:120,1')->name('assets.lookup');
    Route::get('/assets/labels', [AssetController::class, 'labels'])->name('assets.labels');
    Route::put('/assets/{asset}/status', [AssetController::class, 'updateStatus'])->whereNumber('asset')->name('assets.status.update');
    Route::post('/assets/{asset}/condition-reports', [AssetController::class, 'storeConditionReport'])->whereNumber('asset')->name('assets.condition-reports.store');
    Route::resource('assets', AssetController::class)->whereNumber('asset');
});
