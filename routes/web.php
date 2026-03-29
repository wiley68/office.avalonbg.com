<?php

use App\Http\Controllers\Admin\AgentFeedbackStatisticsController;
use App\Http\Controllers\Admin\DataExportController;
use App\Http\Controllers\AgentConversationMessagesController;
use App\Http\Controllers\ContactsAgentController;
use App\Http\Controllers\DashboardAgentController;
use App\Http\Controllers\NotesAgentController;
use App\Http\Controllers\NotesExportDownloadController;
use App\Http\Controllers\Office\TextCryptoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarrantiesAgentController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'office.user'])->group(function () {
    Route::post('dashboard/crypto/encrypt', [TextCryptoController::class, 'encrypt'])
        ->name('dashboard.crypto.encrypt');
    Route::post('dashboard/crypto/decrypt', [TextCryptoController::class, 'decrypt'])
        ->name('dashboard.crypto.decrypt');
});

Route::middleware(['auth', 'verified', 'admin.agent.block'])->group(function () {
    Route::post('dashboard/agent', [DashboardAgentController::class, 'store'])
        ->middleware('agent.context:orchestrator')
        ->name('dashboard.agent');
    Route::get('dashboard/agent/conversations', [AgentConversationMessagesController::class, 'index'])
        ->name('dashboard.agent.conversations');
    Route::delete('dashboard/agent/conversations', [AgentConversationMessagesController::class, 'destroyAll'])
        ->name('dashboard.agent.conversations.destroy');
    Route::get('dashboard/agent/conversations/{conversation}/messages', [AgentConversationMessagesController::class, 'show'])
        ->name('dashboard.agent.conversation.messages');
    Route::post('dashboard/agent/messages/{message}/feedback', [AgentConversationMessagesController::class, 'feedback'])
        ->name('dashboard.agent.message.feedback');
    Route::post('dashboard/agent/messages/{message}/email', [AgentConversationMessagesController::class, 'email'])
        ->name('dashboard.agent.message.email');
    Route::get('dashboard/agent/messages/{message}/pdf', [AgentConversationMessagesController::class, 'pdf'])
        ->name('dashboard.agent.message.pdf');

    Route::inertia('dashboard/notes', 'office/NotesAgent')->name('dashboard.notes');
    Route::inertia('dashboard/contacts', 'office/ContactsAgent')->name('dashboard.contacts');
    Route::get('dashboard/notes/export/{token}', NotesExportDownloadController::class)
        ->name('dashboard.notes.export.download');
    Route::post('dashboard/notes/agent', [NotesAgentController::class, 'store'])
        ->middleware('agent.context:notes')
        ->name('dashboard.notes.agent');
    Route::get('dashboard/notes/agent/conversations', [AgentConversationMessagesController::class, 'index'])
        ->name('dashboard.notes.agent.conversations');
    Route::delete('dashboard/notes/agent/conversations', [AgentConversationMessagesController::class, 'destroyAll'])
        ->name('dashboard.notes.agent.conversations.destroy');
    Route::get('dashboard/notes/agent/conversations/{conversation}/messages', [AgentConversationMessagesController::class, 'show'])
        ->name('dashboard.notes.agent.conversation.messages');
    Route::post('dashboard/notes/agent/messages/{message}/feedback', [AgentConversationMessagesController::class, 'feedback'])
        ->name('dashboard.notes.agent.message.feedback');
    Route::post('dashboard/notes/agent/messages/{message}/email', [AgentConversationMessagesController::class, 'email'])
        ->name('dashboard.notes.agent.message.email');
    Route::get('dashboard/notes/agent/messages/{message}/pdf', [AgentConversationMessagesController::class, 'pdf'])
        ->name('dashboard.notes.agent.message.pdf');

    Route::post('dashboard/contacts/agent', [ContactsAgentController::class, 'store'])
        ->middleware('agent.context:contacts')
        ->name('dashboard.contacts.agent');
    Route::get('dashboard/contacts/agent/conversations', [AgentConversationMessagesController::class, 'index'])
        ->name('dashboard.contacts.agent.conversations');
    Route::delete('dashboard/contacts/agent/conversations', [AgentConversationMessagesController::class, 'destroyAll'])
        ->name('dashboard.contacts.agent.conversations.destroy');
    Route::get('dashboard/contacts/agent/conversations/{conversation}/messages', [AgentConversationMessagesController::class, 'show'])
        ->name('dashboard.contacts.agent.conversation.messages');
    Route::post('dashboard/contacts/agent/messages/{message}/feedback', [AgentConversationMessagesController::class, 'feedback'])
        ->name('dashboard.contacts.agent.message.feedback');
    Route::post('dashboard/contacts/agent/messages/{message}/email', [AgentConversationMessagesController::class, 'email'])
        ->name('dashboard.contacts.agent.message.email');
    Route::get('dashboard/contacts/agent/messages/{message}/pdf', [AgentConversationMessagesController::class, 'pdf'])
        ->name('dashboard.contacts.agent.message.pdf');

    Route::inertia('dashboard/warranties', 'office/WarrantiesAgent')->name('dashboard.warranties');
    Route::post('dashboard/warranties/agent', [WarrantiesAgentController::class, 'store'])
        ->middleware('agent.context:warranties')
        ->name('dashboard.warranties.agent');
    Route::get('dashboard/warranties/agent/conversations', [AgentConversationMessagesController::class, 'index'])
        ->name('dashboard.warranties.agent.conversations');
    Route::delete('dashboard/warranties/agent/conversations', [AgentConversationMessagesController::class, 'destroyAll'])
        ->name('dashboard.warranties.agent.conversations.destroy');
    Route::get('dashboard/warranties/agent/conversations/{conversation}/messages', [AgentConversationMessagesController::class, 'show'])
        ->name('dashboard.warranties.agent.conversation.messages');
    Route::post('dashboard/warranties/agent/messages/{message}/feedback', [AgentConversationMessagesController::class, 'feedback'])
        ->name('dashboard.warranties.agent.message.feedback');
    Route::post('dashboard/warranties/agent/messages/{message}/email', [AgentConversationMessagesController::class, 'email'])
        ->name('dashboard.warranties.agent.message.email');
    Route::get('dashboard/warranties/agent/messages/{message}/pdf', [AgentConversationMessagesController::class, 'pdf'])
        ->name('dashboard.warranties.agent.message.pdf');
});

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::resource('users', UserController::class)->except('show');
    Route::get('dashboard/admin/statistics', AgentFeedbackStatisticsController::class)
        ->name('dashboard.admin.statistics');
    Route::get('dashboard/admin/export/notes', [DataExportController::class, 'notes'])
        ->name('dashboard.admin.export.notes');
    Route::get('dashboard/admin/export', [DataExportController::class, 'index'])
        ->name('dashboard.admin.export');
});

require __DIR__ . '/settings.php';
