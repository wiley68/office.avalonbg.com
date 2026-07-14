<?php

use App\Http\Controllers\Settings\AccessEncryptionKeyController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\EmailController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RequiredPasswordChangeController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/required-password-change', [RequiredPasswordChangeController::class, 'edit'])
        ->name('password.change.edit');

    Route::put('settings/required-password-change', [RequiredPasswordChangeController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.change.update');
});

Route::middleware(['auth', 'verified', 'password.changed'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::post('settings/access-encryption-key', [AccessEncryptionKeyController::class, 'store'])
        ->middleware(['role:user', 'throttle:6,1'])
        ->name('access-encryption-key.store');

    Route::put('settings/access-encryption-key/rotate', [AccessEncryptionKeyController::class, 'rotate'])
        ->middleware(['role:user', 'throttle:6,1'])
        ->name('access-encryption-key.rotate');

    Route::get('settings/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');
    Route::patch('settings/appearance', [AppearanceController::class, 'update'])->name('appearance.update');

    Route::middleware(['role:user'])->group(function () {
        Route::get('settings/email', [EmailController::class, 'edit'])->name('email.edit');
        Route::put('settings/email', [EmailController::class, 'update'])->name('email.update');
        Route::post('settings/email/test', [EmailController::class, 'testConnection'])
            ->middleware('throttle:6,1')
            ->name('email.test');
    });
});
