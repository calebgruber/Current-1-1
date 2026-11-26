<?php

use App\Domain\Rules\Http\Controllers\RuleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('rules')->name('rules.')->group(function () {
    Route::get('/', [RuleController::class, 'index'])->name('index');
    Route::get('/create', [RuleController::class, 'create'])->name('create');
    Route::post('/', [RuleController::class, 'store'])->name('store');
    Route::post('/test', [RuleController::class, 'test'])->middleware('throttle:120,1')->name('test');
    Route::get('/lookup/jobs', [RuleController::class, 'jobs'])->middleware('throttle:120,1')->name('lookup.jobs');
    Route::get('/lookup/products', [RuleController::class, 'products'])->middleware('throttle:120,1')->name('lookup.products');
    Route::get('/{rule}/edit', [RuleController::class, 'edit'])->whereNumber('rule')->name('edit');
    Route::put('/{rule}', [RuleController::class, 'update'])->whereNumber('rule')->name('update');
    Route::delete('/{rule}', [RuleController::class, 'destroy'])->whereNumber('rule')->name('destroy');
    Route::match(['patch', 'post'], '/{rule}/toggle', [RuleController::class, 'toggle'])->whereNumber('rule')->name('toggle');
});
