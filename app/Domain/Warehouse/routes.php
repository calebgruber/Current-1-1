<?php

use App\Domain\Warehouse\Http\Controllers\DamageController;
use App\Domain\Warehouse\Http\Controllers\ScanController;
use App\Domain\Warehouse\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('warehouse')->name('warehouse.')->group(function () {
    Route::get('/', [WarehouseController::class, 'index'])->name('index');
    Route::get('/display/{job?}', [WarehouseController::class, 'display'])->name('display');
    Route::post('/scan', ScanController::class)->middleware('throttle:600,1')->name('scan');
    Route::get('/jobs/{job}/progress', [WarehouseController::class, 'progress'])->name('progress');

    Route::post('/damage', [DamageController::class, 'store'])->name('damage.store');
    Route::get('/damage/reports/{report}/photos/{index}', [DamageController::class, 'photo'])->whereNumber('index')->name('damage.photo');
    Route::post('/jobs/{job}/missing', [DamageController::class, 'missing'])->name('returns.missing');

    foreach (array_keys(WarehouseController::STATIONS) as $station) {
        Route::get("/{$station}/{job?}", [WarehouseController::class, 'station'])
            ->defaults('station', $station)
            ->name($station);
    }
});
