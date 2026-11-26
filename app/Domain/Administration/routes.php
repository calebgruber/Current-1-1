<?php

use App\Domain\Administration\Http\Controllers\SystemController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('admin/system')->name('admin.system.')->group(function () {
    Route::get('/', [SystemController::class, 'index'])->middleware('can:admin.system.view')->name('index');
    Route::post('/cache', [SystemController::class, 'clearCache'])->middleware('can:admin.system.admin')->name('cache.clear');
});
