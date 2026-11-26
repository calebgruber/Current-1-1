<?php

use App\Domain\Settings\Http\Controllers\GeneralSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('settings/general')->name('settings.general.')->group(function () {
    Route::get('/', [GeneralSettingsController::class, 'index'])->middleware('can:settings.general.view')->name('index');
    Route::put('/', [GeneralSettingsController::class, 'update'])->middleware('can:settings.general.edit')->name('update');
});
