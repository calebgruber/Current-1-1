<?php

use App\Domain\Search\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    Route::get('/search/query', [SearchController::class, 'query'])->middleware('throttle:120,1')->name('search.query');
});
