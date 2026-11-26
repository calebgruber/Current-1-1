<?php

use App\Domain\CustomFields\Http\Controllers\CustomFieldInputController;
use Illuminate\Support\Facades\Route;

/*
| Supporting endpoints for custom-field inputs on every module form.
| Definition management (admin) lives under settings.custom-fields.* in the Settings module.
*/
Route::middleware('auth')->prefix('custom-fields')->name('custom-fields.')->group(function () {
    Route::get('/lookup', [CustomFieldInputController::class, 'lookup'])->middleware('throttle:120,1')->name('lookup');
    Route::post('/upload', [CustomFieldInputController::class, 'upload'])->middleware('throttle:30,1')->name('upload');
    Route::get('/download', [CustomFieldInputController::class, 'download'])->name('download');
});
