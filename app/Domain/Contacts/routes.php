<?php

use App\Domain\Contacts\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/contacts/lookup', [ContactController::class, 'lookup'])->middleware('throttle:120,1')->name('contacts.lookup');
    Route::get('/contacts/export', [ContactController::class, 'export'])->name('contacts.export');
    Route::resource('contacts', ContactController::class)->whereNumber('contact');
});
