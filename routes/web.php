<?php

use App\Http\Controllers\AgentConversationMessagesController;
use App\Http\Controllers\DashboardAgentController;
use App\Http\Controllers\NotesAgentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::post('dashboard/agent', [DashboardAgentController::class, 'store'])
        ->middleware('agent.context:orchestrator')
        ->name('dashboard.agent');
    Route::get('dashboard/agent/conversations', [AgentConversationMessagesController::class, 'index'])
        ->name('dashboard.agent.conversations');
    Route::get('dashboard/agent/conversations/{conversation}/messages', [AgentConversationMessagesController::class, 'show'])
        ->name('dashboard.agent.conversation.messages');

    Route::inertia('dashboard/notes', 'office/NotesAgent')->name('dashboard.notes');
    Route::post('dashboard/notes/agent', [NotesAgentController::class, 'store'])
        ->middleware('agent.context:notes')
        ->name('dashboard.notes.agent');
    Route::get('dashboard/notes/agent/conversations', [AgentConversationMessagesController::class, 'index'])
        ->name('dashboard.notes.agent.conversations');
    Route::get('dashboard/notes/agent/conversations/{conversation}/messages', [AgentConversationMessagesController::class, 'show'])
        ->name('dashboard.notes.agent.conversation.messages');
});

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::resource('users', UserController::class)->except('show');
});

require __DIR__ . '/settings.php';
