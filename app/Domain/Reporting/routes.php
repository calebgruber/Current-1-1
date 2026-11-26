<?php

use App\Domain\Reporting\Http\Controllers\ReportController;
use App\Domain\Reporting\Http\Controllers\ReportScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/create', [ReportController::class, 'create'])->name('create');
        Route::post('/', [ReportController::class, 'store'])->name('store');
        Route::post('/run', [ReportController::class, 'run'])->middleware('throttle:120,1')->name('run');
        Route::get('/{report}', [ReportController::class, 'show'])->whereNumber('report')->name('show');
        Route::get('/{report}/edit', [ReportController::class, 'edit'])->whereNumber('report')->name('edit');
        Route::put('/{report}', [ReportController::class, 'update'])->whereNumber('report')->name('update');
        Route::delete('/{report}', [ReportController::class, 'destroy'])->whereNumber('report')->name('destroy');
        Route::get('/{report}/data', [ReportController::class, 'data'])->whereNumber('report')->middleware('throttle:120,1')->name('data');
        Route::get('/{report}/export/{format}', [ReportController::class, 'export'])
            ->whereNumber('report')->whereIn('format', ['csv', 'xlsx', 'pdf'])->middleware('throttle:30,1')->name('export');
        Route::put('/{report}/dashboard', [ReportController::class, 'dashboard'])->whereNumber('report')->name('dashboard');

        Route::get('/{report}/schedules', [ReportScheduleController::class, 'index'])->whereNumber('report')->name('schedules.index');
        Route::post('/{report}/schedules', [ReportScheduleController::class, 'store'])->whereNumber('report')->name('schedules.store');
    });

    Route::put('/report-schedules/{schedule}', [ReportScheduleController::class, 'update'])->whereNumber('schedule')->name('reports.schedules.update');
    Route::delete('/report-schedules/{schedule}', [ReportScheduleController::class, 'destroy'])->whereNumber('schedule')->name('reports.schedules.destroy');
});
