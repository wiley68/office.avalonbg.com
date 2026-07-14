<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\Admin\AgentFeedbackStatisticsController;
use App\Http\Controllers\Admin\DataExportController;
use App\Http\Controllers\AgentConversationMessagesController;
use App\Http\Controllers\Api\AccessApiController;
use App\Http\Controllers\Api\AccessDataTransformController;
use App\Http\Controllers\Api\AuditLogApiController;
use App\Http\Controllers\Api\DocumentApiController;
use App\Http\Controllers\Api\ImapBrowseApiController;
use App\Http\Controllers\Api\ProjectApiController;
use App\Http\Controllers\Api\ProjectEmailApiController;
use App\Http\Controllers\Api\ProjectGitApiController;
use App\Http\Controllers\Api\TaskApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardAgentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotesAgentController;
use App\Http\Controllers\NotesExportDownloadController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\ProjectEmailLinkController;
use App\Http\Controllers\ProjectGitRepositoryController;
use App\Http\Controllers\ProjectRevisionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskDocumentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserTwoFactorController;
use App\Models\Access;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectEmailLink;
use App\Models\ProjectRevision;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('locale/{locale}', LocaleController::class)->name('locale.update');

Route::middleware(['auth', 'verified', 'password.changed', 'two-factor.enabled'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::post('/dashboard/clear-cache', [DashboardController::class, 'clearCache'])
        ->name('dashboard.clear-cache');
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

    Route::post('/users-export', [UserController::class, 'export'])
        ->name('users.export');

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

Route::middleware(['auth', 'verified', 'password.changed', 'two-factor.enabled', 'role:user'])->group(function () {
    Route::bind('access', function (string $value): Access {
        $userId = Auth::id();

        abort_unless($userId !== null, 404);

        return Access::query()
            ->where('user_id', $userId)
            ->findOrFail($value);
    });

    Route::resource('accesses', AccessController::class)
        ->except(['show', 'create', 'edit']);

    Route::prefix('internal-api')
        ->name('internal.')
        ->group(function () {
            Route::get('accesses', [AccessApiController::class, 'index'])
                ->name('accesses.index');

            Route::post('accesses/transform-data', AccessDataTransformController::class)
                ->name('accesses.transform-data');

            Route::get('projects', [ProjectApiController::class, 'index'])
                ->name('projects.index');

            Route::get('projects/{project}/tasks', [TaskApiController::class, 'index'])
                ->name('projects.tasks.index');

            Route::get('projects/{project}/git', [ProjectGitApiController::class, 'show'])
                ->name('projects.git.show');

            Route::get('imap/folders', [ImapBrowseApiController::class, 'folders'])
                ->name('imap.folders.index');

            Route::get('imap/threads', [ImapBrowseApiController::class, 'threads'])
                ->name('imap.threads.index');

            Route::get('imap/attachments', [ImapBrowseApiController::class, 'attachments'])
                ->name('imap.attachments.index');

            Route::get('imap/body', [ImapBrowseApiController::class, 'body'])
                ->name('imap.body.index');

            Route::get('imap/messages', [ImapBrowseApiController::class, 'index'])
                ->name('imap.messages.index');

            Route::get('projects/{project}/email', [ProjectEmailApiController::class, 'index'])
                ->name('projects.email.index');

            Route::get('projects/{project}/email/{emailLink}/body', [ProjectEmailApiController::class, 'body'])
                ->name('projects.email.body');

            Route::get('projects/{project}/email/{emailLink}/attachments/{part}', [ProjectEmailApiController::class, 'downloadAttachment'])
                ->name('projects.email.attachments.download')
                ->where('part', '[0-9]+(?:\.[0-9]+)*');

            Route::get('projects/{project}/email/{emailLink}/attachments', [ProjectEmailApiController::class, 'attachments'])
                ->name('projects.email.attachments.index');

            Route::get('documents', [DocumentApiController::class, 'index'])
                ->name('documents.index');

            Route::post('documents', [DocumentApiController::class, 'store'])
                ->name('documents.store');
        });

    Route::bind('project', function (string $value): Project {
        $userId = Auth::id();

        abort_unless($userId !== null, 404);

        return Project::query()
            ->where('user_id', $userId)
            ->findOrFail($value);
    });

    Route::bind('emailLink', function (string $value): ProjectEmailLink {
        $project = request()->route('project');

        abort_unless($project instanceof Project, 404);

        return ProjectEmailLink::query()
            ->where('project_id', $project->id)
            ->findOrFail($value);
    });

    Route::bind('revision', function (string $value): ProjectRevision {
        $project = request()->route('project');

        abort_unless($project instanceof Project, 404);

        return ProjectRevision::query()
            ->where('project_id', $project->id)
            ->findOrFail($value);
    });

    Route::bind('task', function (string $value): Task {
        $userId = Auth::id();

        abort_unless($userId !== null, 404);

        return Task::query()
            ->where('user_id', $userId)
            ->findOrFail($value);
    });

    Route::bind('document', function (string $value): Document {
        $userId = Auth::id();

        abort_unless($userId !== null, 404);

        return Document::query()
            ->where('user_id', $userId)
            ->findOrFail($value);
    });

    Route::resource('projects', ProjectController::class)
        ->except(['create', 'edit']);

    Route::patch('projects/{project}/revisions/reorder', [ProjectRevisionController::class, 'reorder'])
        ->name('projects.revisions.reorder');
    Route::post('projects/{project}/revisions', [ProjectRevisionController::class, 'store'])
        ->name('projects.revisions.store');
    Route::put('projects/{project}/revisions/{revision}', [ProjectRevisionController::class, 'update'])
        ->name('projects.revisions.update');
    Route::delete('projects/{project}/revisions/{revision}', [ProjectRevisionController::class, 'destroy'])
        ->name('projects.revisions.destroy');

    Route::post('projects/{project}/tasks', [TaskController::class, 'store'])
        ->name('projects.tasks.store');
    Route::patch('projects/{project}/tasks/reorder', [TaskController::class, 'reorderMany'])
        ->name('projects.tasks.reorder');
    Route::put('tasks/{task}', [TaskController::class, 'update'])
        ->name('tasks.update');
    Route::patch('tasks/{task}/complete', [TaskController::class, 'complete'])
        ->name('tasks.complete');
    Route::patch('tasks/{task}/reorder', [TaskController::class, 'reorder'])
        ->name('tasks.reorder');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])
        ->name('tasks.destroy');

    Route::get('documents', [DocumentController::class, 'index'])
        ->name('documents.index');
    Route::post('documents', [DocumentController::class, 'store'])
        ->name('documents.store');
    Route::put('documents/{document}', [DocumentController::class, 'update'])
        ->name('documents.update');
    Route::post('documents/{document}/replace-file', [DocumentController::class, 'replaceFile'])
        ->name('documents.replace-file');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])
        ->name('documents.destroy');
    Route::get('documents/{document}/download', DocumentDownloadController::class)
        ->name('documents.download');

    Route::post('projects/{project}/documents/{document}', [ProjectDocumentController::class, 'store'])
        ->name('projects.documents.store');
    Route::delete('projects/{project}/documents/{document}', [ProjectDocumentController::class, 'destroy'])
        ->name('projects.documents.destroy');

    Route::put('projects/{project}/git', [ProjectGitRepositoryController::class, 'upsert'])
        ->name('projects.git.upsert');
    Route::delete('projects/{project}/git', [ProjectGitRepositoryController::class, 'destroy'])
        ->name('projects.git.destroy');

    Route::get('projects/{project}/email/link', [ProjectEmailLinkController::class, 'link'])
        ->name('projects.email.link');
    Route::post('projects/{project}/email/batch', [ProjectEmailLinkController::class, 'storeBatch'])
        ->name('projects.email.batch.store');
    Route::post('projects/{project}/email', [ProjectEmailLinkController::class, 'store'])
        ->name('projects.email.store');
    Route::delete('projects/{project}/email/{emailLink}', [ProjectEmailLinkController::class, 'destroy'])
        ->name('projects.email.destroy');

    Route::post('tasks/{task}/documents/{document}', [TaskDocumentController::class, 'store'])
        ->name('tasks.documents.store');
    Route::delete('tasks/{task}/documents/{document}', [TaskDocumentController::class, 'destroy'])
        ->name('tasks.documents.destroy');
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
