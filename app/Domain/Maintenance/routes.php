<?php

use App\Domain\Maintenance\Http\Controllers\MaintenanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::resource('maintenance', MaintenanceController::class)->except(['destroy'])->parameters(['maintenance' => 'record'])->whereNumber('record');
});
