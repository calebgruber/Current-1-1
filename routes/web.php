<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
| Module routes live in app/Domain/{Module}/routes.php and are registered by
| App\Providers\ModuleServiceProvider. Only cross-cutting routes belong here.
*/

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
});
