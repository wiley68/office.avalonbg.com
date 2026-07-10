<?php

use App\Http\Controllers\Admin\AgentFeedbackStatisticsController;
use App\Http\Controllers\Admin\DataExportController;
use App\Http\Controllers\AgentConversationMessagesController;
use App\Http\Controllers\Api\AuditLogApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardAgentController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotesAgentController;
use App\Http\Controllers\NotesExportDownloadController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserTwoFactorController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('locale/{locale}', LocaleController::class)->name('locale.update');

Route::middleware(['auth', 'verified', 'password.changed', 'two-factor.enabled'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'password.changed', 'two-factor.enabled', 'profiler.agent.block'])->group(function () {
    Route::inertia('dashboard/composer', 'office/Composer')->name('dashboard.composer');

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
});

Route::middleware(['auth', 'verified', 'password.changed', 'two-factor.enabled', 'role:profiler|admin'])->group(function () {
    Route::resource('users', UserController::class)->except('show');

    Route::post('/users/two-factor/{user}/enable', [UserTwoFactorController::class, 'enable'])
        ->name('users.two-factor.enable');

    Route::delete('/users/two-factor/{user}', [UserTwoFactorController::class, 'disable'])
        ->name('users.two-factor.disable');

    Route::post('/users/two-factor/{user}/resend', [UserTwoFactorController::class, 'resend'])
        ->name('users.two-factor.resend');

    Route::prefix('internal-api')
        ->name('internal.')
        ->group(function () {
            Route::get('users', [UserApiController::class, 'index'])
                ->name('users.index');
        });
});

Route::middleware(['auth', 'verified', 'password.changed', 'two-factor.enabled', 'role:profiler'])->group(function () {
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->name('audit-logs.index');

    Route::post('/audit-logs-export', [AuditLogController::class, 'export'])
        ->name('audit-logs.export');

    Route::delete('/audit-logs/bulk', [AuditLogController::class, 'destroyBulk'])
        ->name('audit-logs.destroy-bulk');

    Route::delete('/audit-logs/{auditLog}', [AuditLogController::class, 'destroy'])
        ->name('audit-logs.destroy');

    Route::prefix('internal-api')
        ->name('internal.')
        ->group(function () {
            Route::get('audit-logs', [AuditLogApiController::class, 'index'])
                ->name('audit-logs.index');
        });
});

Route::middleware(['auth', 'verified', 'password.changed', 'two-factor.enabled', 'role:user|admin'])->group(function () {
    Route::get('dashboard/admin/statistics', AgentFeedbackStatisticsController::class)
        ->name('dashboard.admin.statistics');
    Route::get('dashboard/admin/export/notes', [DataExportController::class, 'notes'])
        ->name('dashboard.admin.export.notes');
    Route::get('dashboard/admin/export', [DataExportController::class, 'index'])
        ->name('dashboard.admin.export');
});

require __DIR__.'/settings.php';
