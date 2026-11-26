<?php

use App\Domain\Documents\Http\Controllers\DocumentTemplateController;
use App\Domain\Documents\Http\Controllers\GeneratedDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('documents')->name('documents.')->group(function () {
    // Templates (static paths first so they never collide with /documents/{document}).
    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [DocumentTemplateController::class, 'index'])->name('index');
        Route::get('/create', [DocumentTemplateController::class, 'create'])->name('create');
        Route::post('/', [DocumentTemplateController::class, 'store'])->name('store');
        Route::post('/preview', [DocumentTemplateController::class, 'preview'])->middleware('throttle:240,1')->name('preview');
        Route::post('/compile', [DocumentTemplateController::class, 'compile'])->middleware('throttle:120,1')->name('compile');
        Route::post('/install-defaults', [DocumentTemplateController::class, 'installDefaults'])->name('install-defaults');
        Route::get('/{template}/edit', [DocumentTemplateController::class, 'edit'])->name('edit');
        Route::put('/{template}', [DocumentTemplateController::class, 'update'])->name('update');
        Route::delete('/{template}', [DocumentTemplateController::class, 'destroy'])->name('destroy');
        Route::post('/{template}/duplicate', [DocumentTemplateController::class, 'duplicate'])->name('duplicate');
        Route::post('/{template}/default', [DocumentTemplateController::class, 'makeDefaultAction'])->name('default');
    });

    Route::get('/', [GeneratedDocumentController::class, 'index'])->name('index');
    Route::get('/entities', [GeneratedDocumentController::class, 'entities'])->middleware('throttle:120,1')->name('entities');
    Route::post('/', [GeneratedDocumentController::class, 'generate'])->middleware('throttle:60,1')->name('store');
    Route::post('/generate', [GeneratedDocumentController::class, 'generate'])->middleware('throttle:60,1')->name('generate');
    Route::get('/{document}', [GeneratedDocumentController::class, 'show'])->whereNumber('document')->name('show');
    Route::put('/{document}', [GeneratedDocumentController::class, 'update'])->whereNumber('document')->name('update');
    Route::get('/{document}/pdf', [GeneratedDocumentController::class, 'pdf'])->whereNumber('document')->name('pdf');
    Route::get('/{document}/html', [GeneratedDocumentController::class, 'html'])->whereNumber('document')->name('html');
    Route::delete('/{document}', [GeneratedDocumentController::class, 'destroy'])->whereNumber('document')->name('destroy');
});
