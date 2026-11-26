<?php

use App\Domain\Authentication\Http\Controllers\Account\PasswordController;
use App\Domain\Authentication\Http\Controllers\Account\ProfileController;
use App\Domain\Authentication\Http\Controllers\Account\SecurityController;
use App\Domain\Authentication\Http\Controllers\Account\SessionController;
use App\Domain\Authentication\Http\Controllers\Account\TwoFactorController;
use App\Domain\Authentication\Http\Controllers\ConfirmPasswordController;
use App\Domain\Authentication\Http\Controllers\LoginController;
use App\Domain\Authentication\Http\Controllers\PasswordResetController;
use App\Domain\Authentication\Http\Controllers\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;

/*
| Authentication + "my account" routes. Passkey ceremonies (passkey.*) are
| registered by laravel/passkeys; see config/passkeys.php.
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:30,1')->name('login.store');

    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:10,1')->name('two-factor.verify');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/confirm-password', [ConfirmPasswordController::class, 'show'])->name('password.confirm');
    Route::post('/confirm-password', [ConfirmPasswordController::class, 'store'])->middleware('throttle:10,1')->name('password.confirm.store');
    Route::get('/confirm-password/status', [ConfirmPasswordController::class, 'status'])->name('password.confirm.status');

    Route::get('/account', [ProfileController::class, 'edit'])->name('account.profile');
    Route::put('/account', [ProfileController::class, 'update'])->name('account.profile.update');

    Route::get('/account/password', [PasswordController::class, 'edit'])->name('account.password');
    Route::put('/account/password', [PasswordController::class, 'update'])->middleware('throttle:10,1')->name('account.password.update');

    Route::prefix('account')->name('account.security')->group(function () {
        Route::get('/security', [SecurityController::class, 'show']);
        Route::get('/sessions', [SecurityController::class, 'sessions'])->name('.sessions');
        Route::delete('/sessions', [SessionController::class, 'destroyOthers'])->middleware('password.confirm')->name('.sessions.destroy-others');
        Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->middleware('password.confirm')->name('.sessions.destroy');

        Route::post('/two-factor', [TwoFactorController::class, 'enable'])->middleware('password.confirm')->name('.two-factor.enable');
        Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('.two-factor.confirm');
        Route::post('/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->middleware('password.confirm')->name('.two-factor.recovery');
        Route::delete('/two-factor', [TwoFactorController::class, 'disable'])->middleware('password.confirm')->name('.two-factor.disable');

        Route::patch('/passkeys/{passkey}', [SessionController::class, 'renamePasskey'])->name('.passkeys.update');
    });
});
