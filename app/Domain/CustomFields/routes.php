<?php

use App\Domain\CustomFields\Http\Controllers\CustomFieldInputController;
use App\Domain\CustomFields\Http\Controllers\JobCustomFieldController;
use Illuminate\Support\Facades\Route;

/*
| Supporting endpoints for custom-field inputs on every module form.
| Definition management currently supports job fields only.
*/
Route::middleware('auth')->prefix('settings/custom-fields')->name('settings.custom-fields.')->group(function () {
    Route::get('/', [JobCustomFieldController::class, 'index'])->name('index');
    Route::post('/', [JobCustomFieldController::class, 'store'])->name('store');
    Route::put('/{definition}', [JobCustomFieldController::class, 'update'])->name('update');
});

Route::middleware('auth')->prefix('custom-fields')->name('custom-fields.')->group(function () {
    Route::get('/lookup', [CustomFieldInputController::class, 'lookup'])->middleware('throttle:120,1')->name('lookup');
    Route::post('/upload', [CustomFieldInputController::class, 'upload'])->middleware('throttle:30,1')->name('upload');
    Route::get('/download', [CustomFieldInputController::class, 'download'])->name('download');
});
