<?php

use App\Domain\Maintenance\Http\Controllers\MaintenanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::post('maintenance/asset-lookup', [MaintenanceController::class, 'lookupAsset'])->name('maintenance.asset-lookup');
    Route::resource('maintenance', MaintenanceController::class)->except(['destroy'])->parameters(['maintenance' => 'record'])->whereNumber('record');
});
