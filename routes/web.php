<?php

use App\Http\Controllers\DashboardAgentController;
use App\Http\Controllers\NotesAgentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::post('dashboard/agent', [DashboardAgentController::class, 'store'])
        ->name('dashboard.agent');

    Route::inertia('dashboard/notes', 'office/NotesAgent')->name('dashboard.notes');
    Route::post('dashboard/notes/agent', [NotesAgentController::class, 'store'])
        ->name('dashboard.notes.agent');
});

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::resource('users', UserController::class)->except('show');
});

require __DIR__ . '/settings.php';
