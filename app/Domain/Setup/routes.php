<?php

use App\Domain\Setup\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

/*
| Web installer. EnsureApplicationInstalled returns 404 for these routes once
| installed; PrepareRuntimeConfiguration switches session/cache to files so the
| wizard works before a database exists.
*/
Route::prefix('setup')->name('setup.')->middleware('throttle:60,1')->group(function () {
    Route::get('/', [SetupController::class, 'show'])->name('index');
    Route::post('/requirements', [SetupController::class, 'requirements'])->name('requirements');
    Route::post('/database/test', [SetupController::class, 'testDatabase'])->name('database.test');
    Route::post('/database', [SetupController::class, 'database'])->name('database');
    Route::post('/application', [SetupController::class, 'application'])->name('application');
    Route::post('/install', [SetupController::class, 'install'])->name('install');
    Route::post('/admin', [SetupController::class, 'admin'])->name('admin');
    Route::post('/finish', [SetupController::class, 'finish'])->name('finish');
});
