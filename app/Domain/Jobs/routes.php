<?php

use App\Domain\Jobs\Http\Controllers\JobController;
use App\Domain\Jobs\Http\Controllers\JobFileController;
use App\Domain\Jobs\Http\Controllers\JobItemController;
use App\Domain\Jobs\Http\Controllers\JobStageController;
use App\Domain\Jobs\Http\Controllers\LookupController;
use App\Domain\Jobs\Http\Controllers\PullSheetController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/jobs/lookup/{type}', LookupController::class)
        ->whereIn('type', ['products', 'contacts', 'locations', 'users', 'jobs', 'assets'])
        ->middleware('throttle:120,1')
        ->name('jobs.lookup');

    Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/create', [JobController::class, 'create'])->name('jobs.create');
    Route::post('/jobs', [JobController::class, 'store'])->name('jobs.store');
    Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
    Route::get('/jobs/{job}/edit', [JobController::class, 'edit'])->name('jobs.edit');
    Route::put('/jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
    Route::delete('/jobs/{job}', [JobController::class, 'destroy'])->name('jobs.destroy');

    Route::post('/jobs/{job}/stage', JobStageController::class)->name('jobs.transition');
    Route::get('/jobs/{job}/pull-sheet', PullSheetController::class)->name('jobs.pull-sheet');
    Route::get('/jobs/{job}/conflicts', [JobItemController::class, 'conflicts'])->name('jobs.conflicts');
    Route::get('/jobs/{job}/validation', [JobItemController::class, 'validation'])->name('jobs.validation');

    Route::get('/jobs/{job}/items/check', [JobItemController::class, 'check'])->name('jobs.items.check');
    Route::post('/jobs/{job}/items', [JobItemController::class, 'store'])->name('jobs.items.store');
    Route::put('/jobs/{job}/items/{item}', [JobItemController::class, 'update'])->name('jobs.items.update');
    Route::delete('/jobs/{job}/items/{item}', [JobItemController::class, 'destroy'])->name('jobs.items.destroy');
    Route::get('/jobs/{job}/items/{item}/assets', [JobItemController::class, 'assetOptions'])->name('jobs.items.asset-options');
    Route::put('/jobs/{job}/items/{item}/assets', [JobItemController::class, 'assignAssets'])->name('jobs.items.assets');

    Route::post('/jobs/{job}/notes', [JobFileController::class, 'storeNote'])->name('jobs.notes.store');
    Route::delete('/jobs/{job}/notes/{note}', [JobFileController::class, 'destroyNote'])->name('jobs.notes.destroy');
    Route::post('/jobs/{job}/attachments', [JobFileController::class, 'storeAttachment'])->name('jobs.attachments.store');
    Route::get('/jobs/{job}/attachments/{attachment}', [JobFileController::class, 'download'])->name('jobs.attachments.download');
    Route::delete('/jobs/{job}/attachments/{attachment}', [JobFileController::class, 'destroyAttachment'])->name('jobs.attachments.destroy');
});
