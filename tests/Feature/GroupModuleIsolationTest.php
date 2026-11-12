<?php

use App\Models\Access;
use App\Models\CalendarEvent;
use App\Models\Document;
use App\Models\ProfitEntry;
use App\Models\ProfitType;
use App\Models\Project;
use App\Models\ProjectEmailAttachment;
use App\Models\ProjectEmailLink;
use App\Models\ProjectRevision;
use App\Models\ProjectTodo;
use App\Models\Task;
use App\Models\User;
use App\Models\UserImapAccount;
use App\Services\AccessEncryptionService;
use App\Services\DocumentStorageService;
use App\Services\DocumentUsageService;
use App\Services\ImapMailboxService;
use App\Services\ProfitsExportDataBuilder;
use App\Services\ProjectEmailArchiveService;
use App\Services\ProjectEmailService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('local');
    Http::preventStrayRequests();
    $this->creator = User::factory()->officeUser()->create();
    $this->peer = User::factory()->officeUser($this->creator->group)->create();
    $this->outsider = User::factory()->officeUser()->create();
    $this->actingAs($this->creator);
});

dataset('group modules', [
    'projects' => ['projects', Project::class],
    'documents' => ['documents', Document::class],
    'calendar' => ['calendar-events', CalendarEvent::class],
    'accesses' => ['accesses', Access::class],
    'profit' => ['profits', ProfitEntry::class],
]);

/** @return array<string, mixed> */
function groupModulePayload(string $module, User $user, ?Model $record = null): array
{
    return match ($module) {
        'projects' => ['name' => 'Shared project', 'status' => 'active', 'description' => 'Shared description'],
        'documents' => $record === null
            ? ['file' => UploadedFile::fake()->create('shared.pdf', 10, 'application/pdf'), 'description' => 'Shared document']
            : ['description' => 'Peer update'],
        'calendar-events' => ['title' => 'Shared event', 'starts_at' => '2026-11-12T09:00', 'ends_at' => '2026-11-12T10:00', 'type' => 'meeting', 'priority' => 'standard', 'status' => 'active'],
        'accesses' => [...accessFormState($record), 'name' => 'Shared access', 'category' => 'Shared category', 'content' => 'Shared plaintext', 'is_encrypted' => false],
        'profits' => ['profit_type_id' => $record?->profit_type_id ?? ProfitType::factory()->for($user->group)->income()->create()->id, 'date' => '2026-11-12', 'amount' => '150.25', 'description' => 'Shared profit'],
    };
}

/** @return array<string, mixed> */
function groupModuleIndexParameters(string $module): array
{
    return match ($module) {
        'calendar-events' => ['from' => '2000-01-01', 'to' => '2100-01-01'],
        'profits' => ['month' => '2026-11'],
        default => ['per_page' => 1, 'search' => 'Shared'],
    };
}

test('same-group peers share module CRUD and forged ownership never changes creator', function (string $module, string $modelClass): void {
    $payload = groupModulePayload($module, $this->creator);
    $this->actingAs($this->creator)->post(route($module.'.store'), [
        ...$payload, 'group_id' => $this->outsider->group_id, 'user_id' => $this->outsider->id,
    ])->assertSessionHasNoErrors()->assertRedirect();
    $record = $modelClass::query()->sole();
    expect($record->group_id)->toBe($this->creator->group_id)->and($record->user_id)->toBe($this->creator->id);

    $response = $this->actingAs($this->peer)->getJson(route('internal.'.$module.'.index', groupModuleIndexParameters($module)))->assertOk();
    if ($module === 'calendar-events') {
        $response->assertJsonPath('0.id', $record->id);
    } elseif ($module === 'profits') {
        $response->assertJsonPath('income.0.id', $record->id);
    } else {
        $response->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $record->id);
    }
    if ($module === 'projects') {
        $this->get(route('projects.show', $record))->assertOk();
    } elseif ($module === 'documents') {
        expect($record->storage_path)->toStartWith('documents/groups/'.$this->creator->group_id.'/');
        $this->get(route('documents.download', $record))->assertOk();
        $this->post(route('documents.replace-file', $record), ['file' => UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf')])->assertRedirect();
        expect($record->fresh()->storage_path)->toStartWith('documents/groups/'.$this->creator->group_id.'/');
    }
    $this->put(route($module.'.update', $record), [
        ...groupModulePayload($module, $this->creator, $record),
        'group_id' => $this->outsider->group_id, 'user_id' => $this->peer->id,
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($record->fresh()->group_id)->toBe($this->creator->group_id)->and($record->fresh()->user_id)->toBe($this->creator->id);
    $this->delete(route($module.'.destroy', $record), $module === 'accesses' ? accessFormState($record->fresh()) : [])->assertSessionHasNoErrors()->assertRedirect();
    expect($record->fresh())->toBeNull();
})->with('group modules');

test('other groups cannot list search mutate delete or follow direct module URLs', function (string $module, string $modelClass): void {
    $this->actingAs($this->creator)->post(route($module.'.store'), groupModulePayload($module, $this->creator))->assertRedirect();
    $record = $modelClass::query()->sole();
    $response = $this->actingAs($this->outsider)->getJson(route('internal.'.$module.'.index', groupModuleIndexParameters($module)))->assertOk();
    if ($module === 'calendar-events') {
        $response->assertExactJson([]);
    } elseif ($module === 'profits') {
        $response->assertJsonCount(0, 'income')->assertJsonCount(0, 'expense')->assertJsonPath('totals.income', '0.00');
    } else {
        $response->assertJsonPath('total', 0)->assertJsonCount(0, 'data');
    }
    $this->putJson(route($module.'.update', $record), groupModulePayload($module, $this->creator, $record))->assertNotFound();
    $this->deleteJson(route($module.'.destroy', $record))->assertNotFound();
    if ($module === 'projects') {
        $this->getJson(route('projects.show', $record))->assertNotFound();
    } elseif ($module === 'documents') {
        $this->getJson(route('documents.download', $record))->assertNotFound();
        $this->postJson(route('documents.replace-file', $record), [])->assertNotFound();
    }
    expect($record->fresh())->not->toBeNull();
})->with('group modules');

test('reassignment removes access to old records even for their creator', function (string $module, string $modelClass): void {
    $record = $modelClass::factory()->for($this->creator)->create();
    $oldGroupId = $record->group_id;
    $this->creator->group()->associate($this->outsider->group);
    $this->creator->save();
    $this->actingAs($this->creator->fresh());
    expect($this->creator->can('view', $record))->toBeFalse()->and($this->creator->can('update', $record))->toBeFalse();
    $this->putJson(route($module.'.update', $record), [])->assertNotFound();
    $this->deleteJson(route($module.'.destroy', $record))->assertNotFound();
    expect($record->fresh()->group_id)->toBe($oldGroupId)->and($record->fresh()->user_id)->toBe($this->creator->id);
    expect($this->peer->can('update', $record))->toBeTrue();
})->with('group modules');

test('module policies reject null membership privileged roles and removed roles independently of middleware', function (string $module, string $modelClass): void {
    $record = $modelClass::factory()->for($this->creator)->create();
    $missing = User::factory()->create();
    $missing->assignRole('user');
    $removed = User::factory()->officeUser($this->creator->group)->create();
    $removed->syncRoles([]);
    $actors = [$missing, $removed];
    foreach (['admin', 'profiler'] as $role) {
        $actor = User::factory()->create(['group_id' => $record->group_id]);
        $actor->assignRole(Role::findOrCreate($role, 'web'));
        $actors[] = $actor;
    }
    foreach ($actors as $actor) {
        foreach (['viewAny', 'create'] as $ability) {
            expect($actor->can($ability, $modelClass))->toBeFalse();
        }
        foreach (['view', 'update', 'delete'] as $ability) {
            expect($actor->can($ability, $record))->toBeFalse();
        }
        $this->actingAs($actor)->getJson(route('internal.'.$module.'.index', groupModuleIndexParameters($module)))->assertForbidden();
    }
    $nullRecord = new $modelClass;
    expect($missing->can('update', $nullRecord))->toBeFalse();
})->with('group modules');

test('nested task revision todo and Git mutations are shared while original task creators survive', function (): void {
    $project = Project::factory()->for($this->creator)->create();
    $task = Task::factory()->for($project)->for($this->creator)->create();
    $revision = ProjectRevision::factory()->for($project)->create();
    $todo = ProjectTodo::factory()->for($project)->create();
    $this->actingAs($this->peer)->getJson(route('internal.projects.tasks.index', $project))->assertOk()->assertJsonPath('data.0.id', $task->id);
    $this->put(route('tasks.update', $task), ['name' => 'Peer task edit', 'status' => 'active', 'project_revision_id' => $revision->id, 'user_id' => $this->peer->id, 'group_id' => $this->outsider->group_id])->assertRedirect();
    expect($task->fresh()->user_id)->toBe($this->creator->id)->and($task->fresh()->group_id)->toBe($project->group_id);
    $this->post(route('projects.tasks.store', $project), ['name' => 'Peer-created task', 'status' => 'active', 'user_id' => $this->creator->id, 'group_id' => $this->outsider->group_id])->assertRedirect();
    $created = Task::query()->where('name', 'Peer-created task')->sole();
    expect($created->user_id)->toBe($this->peer->id)->and($created->group_id)->toBe($project->group_id);
    $this->put(route('projects.revisions.update', [$project, $revision]), ['label' => 'Peer stage', 'description' => 'Updated'])->assertRedirect();
    $this->put(route('projects.todos.update', [$project, $todo]), ['body' => 'Peer todo'])->assertRedirect();
    $this->patch(route('projects.todos.toggle', [$project, $todo]))->assertRedirect();
    $this->put(route('projects.git.upsert', $project), ['repository' => 'https://github.com/example/shared', 'default_branch' => 'main'])->assertRedirect();
    expect($project->fresh()->gitRepository->repo)->toBe('shared')->and($todo->fresh()->completed_at)->not->toBeNull();
    $this->delete(route('projects.git.destroy', $project))->assertRedirect();
    $this->delete(route('projects.todos.destroy', [$project, $todo]))->assertRedirect();
    $this->delete(route('tasks.destroy', $created))->assertRedirect();
});

test('cross-group nested URLs and mixed-ID batches refuse the entire mutation', function (): void {
    $project = Project::factory()->for($this->creator)->create();
    $foreign = Project::factory()->for($this->outsider)->create();
    $localTask = Task::factory()->for($project)->create(['sort_order' => 4]);
    $foreignTask = Task::factory()->for($foreign)->create();
    $localRevision = ProjectRevision::factory()->for($project)->create(['sort_order' => 4]);
    $foreignRevision = ProjectRevision::factory()->for($foreign)->create();
    $foreignTodo = ProjectTodo::factory()->for($foreign)->create();
    $localLink = ProjectEmailLink::factory()->for($project)->create();
    $foreignLink = ProjectEmailLink::factory()->for($foreign)->create();
    $this->actingAs($this->peer);
    $this->patchJson(route('projects.tasks.reorder', $project), ['task_ids' => [$localTask->id, $foreignTask->id], 'parent_id' => null])->assertUnprocessable();
    $this->patchJson(route('projects.revisions.reorder', $project), ['revision_ids' => [$localRevision->id, $foreignRevision->id]])->assertUnprocessable();
    $this->deleteJson(route('projects.email.thread.destroy', $project), ['link_ids' => [$localLink->id, $foreignLink->id]])->assertUnprocessable();
    $this->putJson(route('tasks.update', $localTask), ['name' => 'Blocked', 'status' => 'active', 'parent_id' => $foreignTask->id, 'project_revision_id' => $foreignRevision->id])->assertUnprocessable();
    $this->patchJson(route('tasks.reorder', $localTask), ['parent_id' => $foreignTask->id, 'sort_order' => 0])->assertUnprocessable();
    $this->putJson(route('projects.revisions.update', [$project, $foreignRevision]), ['label' => 'Blocked'])->assertNotFound();
    $this->deleteJson(route('projects.todos.destroy', [$project, $foreignTodo]))->assertNotFound();
    expect($localTask->fresh()->sort_order)->toBe(4)->and($localRevision->fresh()->sort_order)->toBe(4)->and($localLink->fresh())->not->toBeNull();
    $this->actingAs($this->outsider);
    foreach (['internal.projects.tasks.index', 'internal.projects.git.show', 'internal.projects.email.index'] as $route) {
        $this->getJson(route($route, $project))->assertNotFound();
    }
    $this->patchJson(route('tasks.complete', $localTask))->assertNotFound();
    $this->putJson(route('projects.git.upsert', $project), ['repository' => 'example/blocked'])->assertNotFound();
});

test('documents from different creators associate within their group and foreign associations fail', function (): void {
    $project = Project::factory()->for($this->creator)->create();
    $task = Task::factory()->for($project)->for($this->creator)->create();
    $document = Document::factory()->for($this->peer)->create();
    $foreign = Document::factory()->for($this->outsider)->create();
    $this->actingAs($this->peer);
    foreach (['projects.documents' => $project, 'tasks.documents' => $task] as $prefix => $parent) {
        $this->post(route($prefix.'.store', [$parent, $document]))->assertRedirect();
        $this->postJson(route($prefix.'.store', [$parent, $foreign]))->assertNotFound();
        $this->deleteJson(route($prefix.'.destroy', [$parent, $foreign]))->assertNotFound();
    }
    expect($project->documents()->count())->toBe(1)->and($task->documents()->count())->toBe(1);
    $usages = app(DocumentUsageService::class)->usages($document);
    expect($usages)->toHaveCount(2);
    $this->actingAs($this->outsider);
    expect(fn () => app(DocumentUsageService::class)->usages($document))->toThrow(AuthorizationException::class);
    expect(fn () => app(DocumentStorageService::class)->download($document))->toThrow(AuthorizationException::class);
    expect(fn () => app(DocumentStorageService::class)->delete($document))->toThrow(AuthorizationException::class);
    expect($document->fresh())->not->toBeNull();
});

test('profit types totals statistics and both export data sources use only the current group', function (): void {
    $localType = ProfitType::factory()->for($this->creator->group)->income()->create(['name' => 'Same category']);
    $foreignType = ProfitType::factory()->for($this->outsider->group)->income()->create(['name' => 'Same category']);
    $entry = ProfitEntry::factory()->for($this->peer)->for($localType, 'profitType')->create(['date' => '2026-11-12', 'amount' => '150.25', 'description' => 'PEER-EXPORT-MARKER']);
    ProfitEntry::factory()->for($this->outsider)->for($foreignType, 'profitType')->create(['date' => '2026-11-12', 'amount' => '99999.00', 'description' => 'FOREIGN-EXPORT-MARKER']);
    $this->actingAs($this->creator)->getJson(route('internal.profit-types.index'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $localType->id);
    $this->getJson(route('internal.profits.index', ['month' => '2026-11']))->assertJsonPath('income.0.id', $entry->id)->assertJsonPath('totals.income', '150.25');
    $this->getJson(route('internal.profits.stats', ['year' => 2026]))->assertJsonPath('totals.income', '150.25');
    $this->postJson(route('profits.store'), ['profit_type_id' => $foreignType->id, 'date' => '2026-11-12', 'amount' => 1])->assertJsonValidationErrors('profit_type_id');
    $this->putJson(route('profits.update', $entry), ['profit_type_id' => $foreignType->id, 'date' => '2026-11-12', 'amount' => 1])->assertJsonValidationErrors('profit_type_id');
    $data = app(ProfitsExportDataBuilder::class)->build($this->creator, '2026-11-01', '2026-11-30');
    expect($data['income']->modelKeys())->toBe([$entry->id])->and($data['totals']['income'])->toBe('150.25');
    $response = $this->postJson(route('profits.export'), ['date_from' => '2026-11-01', 'date_to' => '2026-11-30', 'format' => 'xlsx'])->assertOk();
    $exportPath = $response->baseResponse->getFile()->getPathname();
    $spreadsheet = IOFactory::load($exportPath);
    $cells = collect($spreadsheet->getActiveSheet()->toArray())->flatten()->all();
    expect($cells)->toContain('PEER-EXPORT-MARKER')->not->toContain('FOREIGN-EXPORT-MARKER');
    $spreadsheet->disconnectWorksheets();
    unlink($exportPath);
    expect($this->outsider->can('view', $localType))->toBeFalse()->and($this->outsider->can('update', $localType))->toBeFalse()->and($this->outsider->can('delete', $localType))->toBeFalse();
    $this->postJson(route('profit-types.store'), ['name' => 'Same category', 'kind' => 'income'])->assertJsonValidationErrors('name');
    $this->actingAs($this->creator)->post(route('profit-types.store'), ['name' => 'Another shared name', 'kind' => 'income'])->assertRedirect();
    $this->actingAs($this->outsider)->post(route('profit-types.store'), ['name' => 'Another shared name', 'kind' => 'income'])->assertRedirect();
});

test('encrypted Accesses are shared by group and preserve the original creator', function (): void {
    $service = app(AccessEncryptionService::class);
    $context = $service->context();
    $ciphertext = $service->encryptData('secret', $context);
    $record = Access::factory()->for($this->creator)->create(['content' => $ciphertext, 'is_encrypted' => true]);
    $this->actingAs($this->peer)->getJson(route('internal.accesses.index'))->assertOk()->assertJsonPath('data.0.content', $ciphertext);
    $this->postJson(route('internal.accesses.transform-data'), [...accessFormState($record), 'access_id' => $record->id, 'action' => 'decrypt', 'content' => $ciphertext, 'is_encrypted' => true])->assertOk()->assertJsonPath('content', 'secret');
    $updated = $service->encryptData('peer edit', $context, $record, $record->version());
    $this->put(route('accesses.update', $record), [...groupModulePayload('accesses', $this->peer, $record), 'content' => $updated, 'is_encrypted' => true])->assertSessionHasNoErrors()->assertRedirect();
    expect($record->fresh()->user_id)->toBe($this->creator->id);
    $this->actingAs($this->outsider)->postJson(route('internal.accesses.transform-data'), [...accessFormState(), 'access_id' => $record->id, 'version' => $record->fresh()->version(), 'action' => 'decrypt', 'content' => $updated, 'is_encrypted' => true])->assertJsonValidationErrors('access_id');
    $this->actingAs($this->peer)->delete(route('accesses.destroy', $record), accessFormState($record->fresh()))->assertRedirect();
});

test('reassigned creator can rotate only the new group and cannot change old-group Accesses', function (): void {
    $service = app(AccessEncryptionService::class);
    $context = $service->context();
    $ciphertext = $service->encryptData('secret', $context);
    $record = Access::factory()->for($this->creator)->create(['content' => $ciphertext, 'is_encrypted' => true]);
    $this->creator->group()->associate($this->outsider->group);
    $this->creator->save();
    $this->actingAs($this->creator->fresh())->put(route('access-encryption-key.rotate'), [...accessFormState(), 'current_password' => 'Password123!'])->assertSessionHasNoErrors()->assertRedirect();
    expect($record->fresh()->content)->toBe($ciphertext);
});

test('archived project mail and attachments are shared by group without mailbox access', function (): void {
    $project = Project::factory()->for($this->creator)->create();
    $link = ProjectEmailLink::factory()->for($project)->archived()->create(['body_text' => 'Shared archived body']);
    $attachment = ProjectEmailAttachment::factory()->for($link, 'emailLink')->create();
    Storage::disk('local')->put($attachment->document->storage_path, 'shared attachment');
    $this->mock(ImapMailboxService::class)->shouldNotReceive('fetchMessageBody', 'fetchMessageAttachments', 'fetchAttachmentPart');
    $this->actingAs($this->peer)->getJson(route('internal.projects.email.index', $project))->assertOk();
    $this->getJson(route('internal.projects.email.body', [$project, $link]))->assertJsonPath('data.text', 'Shared archived body');
    $this->getJson(route('internal.projects.email.attachments.index', [$project, $link]))->assertOk()->assertJsonCount(1, 'data');
    $this->get(route('internal.projects.email.attachments.download', [$project, $link, $attachment->imap_part]))->assertOk();
    $this->actingAs($this->outsider)->getJson(route('internal.projects.email.attachments.download', [$project, $link, $attachment->imap_part]))->assertNotFound();
    $this->getJson(route('internal.projects.email.body', [$project, $link]))->assertNotFound();
    $this->actingAs($this->peer)->delete(route('projects.email.destroy', [$project, $link]))->assertRedirect();
    expect($attachment->document->fresh())->not->toBeNull();
});

test('live mail fallback refuses and a failed own mailbox archive leaves other sources untouched', function (): void {
    $project = Project::factory()->for($this->creator)->create();
    $link = ProjectEmailLink::factory()->for($project)->create();
    $account = UserImapAccount::factory()->for($this->peer)->create();
    $imap = $this->mock(ImapMailboxService::class);
    $imap->shouldNotReceive('fetchMessageBody', 'fetchMessageAttachments', 'fetchAttachmentPart', 'fetchMessageSummary');
    $imap->shouldReceive('folderUidValidity')
        ->with(Mockery::on(fn (UserImapAccount $candidate): bool => $candidate->is($account)), 'INBOX')
        ->times(3)
        ->andThrow(new RuntimeException('Personal mailbox unavailable'));
    $this->actingAs($this->peer)->getJson(route('internal.projects.email.index', $project))->assertOk()->assertJsonPath('data.threads.0.messages.0.can_read', false);
    foreach (['internal.projects.email.body', 'internal.projects.email.attachments.index'] as $route) {
        $this->getJson(route($route, [$project, $link]))->assertForbidden();
    }
    $this->getJson(route('internal.projects.email.attachments.download', [$project, $link, '2']))->assertForbidden();
    $message = ['folder' => 'INBOX', 'imap_uid' => $link->imap_uid, 'uidvalidity' => $link->uidvalidity];
    $this->postJson(route('projects.email.store', $project), $message)->assertUnprocessable();
    $this->postJson(route('projects.email.batch.store', $project), ['messages' => [['folder' => 'INBOX', 'imap_uid' => 9999, 'uidvalidity' => 1], $message]])->assertUnprocessable();
    expect(app(ProjectEmailService::class)->refreshLinks($project, $this->peer))->toHaveCount(1);
    expect(fn () => app(ProjectEmailArchiveService::class)->archiveMessage($this->peer, $project, $account, $message))->toThrow(HttpException::class);
    expect($link->fresh()->user_id)->toBe($this->creator->id)->and($project->emailLinks()->count())->toBe(1);
});

test('pagination and search count both group creators while ignoring forged group filters', function (string $module, string $modelClass, string $field): void {
    foreach ([$this->creator, $this->peer, $this->outsider] as $actor) {
        $modelClass::factory()->for($actor)->create([$field => 'Matching shared record']);
    }
    $this->actingAs($this->creator)->getJson(route('internal.'.$module.'.index', [
        'search' => 'Matching', 'per_page' => 1, 'page' => 2,
        'group_id' => $this->outsider->group_id, 'user_id' => $this->outsider->id,
    ]))->assertOk()->assertJsonPath('total', 2)->assertJsonPath('last_page', 2)->assertJsonCount(1, 'data');
})->with([
    ['projects', Project::class, 'name'],
    ['documents', Document::class, 'original_name'],
    ['accesses', Access::class, 'name'],
]);

test('peer reordering and deletion of another creators tasks do not change authorship', function (): void {
    $project = Project::factory()->for($this->creator)->create();
    $first = Task::factory()->for($project)->create(['sort_order' => 0]);
    $second = Task::factory()->for($project)->create(['sort_order' => 1]);
    $r1 = ProjectRevision::factory()->for($project)->create(['sort_order' => 0]);
    $r2 = ProjectRevision::factory()->for($project)->create(['sort_order' => 1]);
    $this->actingAs($this->peer)->patch(route('projects.tasks.reorder', $project), ['parent_id' => null, 'task_ids' => [$second->id, $first->id]])->assertSessionHasNoErrors()->assertRedirect();
    $this->patch(route('projects.revisions.reorder', $project), ['revision_ids' => [$r2->id, $r1->id]])->assertSessionHasNoErrors()->assertRedirect();
    expect($second->fresh()->sort_order)->toBe(0)->and($second->fresh()->user_id)->toBe($this->creator->id)->and($r2->fresh()->sort_order)->toBe(0);
    $this->patch(route('tasks.complete', $first))->assertRedirect();
    expect($first->fresh()->completed_at)->not->toBeNull()->and($first->fresh()->user_id)->toBe($this->creator->id);
    $this->delete(route('tasks.destroy', $first))->assertRedirect();
    expect($first->fresh())->toBeNull();
});

test('document replacement uses the record group after the creator leaves and legacy downloads still work', function (): void {
    $record = Document::factory()->for($this->creator)->create(['storage_path' => 'documents/legacy-owner/file.pdf']);
    Storage::disk('local')->put($record->storage_path, 'legacy content');
    $oldGroupId = $record->group_id;
    $this->creator->group()->associate($this->outsider->group);
    $this->creator->save();
    $this->actingAs($this->peer)->get(route('documents.download', $record))->assertOk();
    $this->post(route('documents.replace-file', $record), ['file' => UploadedFile::fake()->create('peer.pdf', 10, 'application/pdf')])->assertRedirect();
    expect($record->fresh()->storage_path)->toStartWith('documents/groups/'.$oldGroupId.'/')
        ->and($record->fresh()->group_id)->toBe($oldGroupId)->and($record->fresh()->user_id)->toBe($this->creator->id);
    Storage::disk('local')->assertMissing('documents/legacy-owner/file.pdf');
    $this->actingAs($this->creator->fresh())->getJson(route('documents.download', $record))->assertNotFound();
});

test('revoked and deactivated users lose module HTTP access with old record URLs', function (): void {
    $project = Project::factory()->for($this->creator)->create();
    $this->peer->syncRoles([]);
    $this->actingAs($this->peer)->getJson(route('projects.show', $project))->assertNotFound();
    $this->creator->update(['status' => 0]);
    $this->actingAs($this->creator)->getJson(route('projects.show', $project))->assertForbidden();
    $this->assertGuest();
    expect($project->fresh())->not->toBeNull();
});
