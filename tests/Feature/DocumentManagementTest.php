<?php

use App\Models\Document;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('user', 'web');
    Storage::fake('local');
});

test('office user can upload update download and delete documents', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    $file = UploadedFile::fake()->create('requirements.pdf', 120, 'application/pdf');

    post(route('documents.store'), [
        'file' => $file,
        'description' => 'Business requirements',
    ])->assertRedirect();

    $document = Document::query()->firstOrFail();

    expect($document)
        ->original_name->toBe('requirements.pdf')
        ->description->toBe('Business requirements');

    Storage::assertExists($document->storage_path);

    put(route('documents.update', $document), [
        'description' => 'Updated description',
    ])->assertRedirect();

    expect($document->fresh()->description)->toBe('Updated description');

    get(route('documents.download', $document))->assertOk();

    delete(route('documents.destroy', $document))->assertRedirect();

    expect(Document::query()->count())->toBe(0);
    Storage::assertMissing($document->storage_path);
});

test('office user can attach the same document to project and task', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->for($project)->for($user)->create();
    $document = Document::factory()->for($user)->create();

    Storage::disk('local')->put($document->storage_path, 'demo');

    actingAs($user);

    post(route('projects.documents.store', [$project, $document]))
        ->assertRedirect();

    post(route('tasks.documents.store', [$task, $document]))
        ->assertRedirect();

    expect($project->fresh()->documents)->toHaveCount(1)
        ->and($task->fresh()->documents)->toHaveCount(1);

    delete(route('projects.documents.destroy', [$project, $document]))
        ->assertRedirect();

    expect($project->fresh()->documents)->toHaveCount(0)
        ->and($task->fresh()->documents)->toHaveCount(1);
});

test('office user cannot access another users document', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');

    $other = User::factory()->create();
    $other->assignRole('user');

    $document = Document::factory()->for($owner)->create();

    actingAs($other);

    get(route('documents.download', $document))->assertNotFound();
    delete(route('documents.destroy', $document))->assertNotFound();
});

test('documents api returns only current user records', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    Document::factory()->for($user)->count(2)->create();

    $other = User::factory()->create();
    $other->assignRole('user');
    Document::factory()->for($other)->create();

    actingAs($user)
        ->getJson(route('internal.documents.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('viewable documents are served inline in the browser', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $document = Document::factory()->for($user)->create([
        'mime_type' => 'application/pdf',
        'original_name' => 'report.pdf',
    ]);

    Storage::disk('local')->put($document->storage_path, '%PDF-1.4');

    actingAs($user)
        ->get(route('documents.download', $document))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="report.pdf"; filename*=UTF-8\'\'report.pdf');
});

test('office documents are served as attachments', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $document = Document::factory()->for($user)->create([
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'original_name' => 'spec.docx',
    ]);

    Storage::disk('local')->put($document->storage_path, 'docx-content');

    actingAs($user)
        ->get(route('documents.download', $document))
        ->assertOk()
        ->assertHeader(
            'Content-Disposition',
            'attachment; filename="spec.docx"; filename*=UTF-8\'\'spec.docx',
        );
});

test('viewable documents can be forced to download as attachments', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $document = Document::factory()->for($user)->create([
        'mime_type' => 'application/pdf',
        'original_name' => 'report.pdf',
    ]);

    Storage::disk('local')->put($document->storage_path, '%PDF-1.4');

    actingAs($user)
        ->get(route('documents.download', ['document' => $document, 'download' => 1]))
        ->assertOk()
        ->assertHeader(
            'Content-Disposition',
            'attachment; filename="report.pdf"; filename*=UTF-8\'\'report.pdf',
        );
});

test('office user can replace document file while keeping id and attachments', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->for($project)->for($user)->create();

    $document = Document::factory()->for($user)->create([
        'original_name' => 'old.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => 3,
    ]);

    Storage::disk('local')->put($document->storage_path, 'old');

    $project->documents()->attach($document);
    $task->documents()->attach($document);

    $documentId = $document->id;
    $oldPath = $document->storage_path;

    actingAs($user);

    $newFile = UploadedFile::fake()->create('updated.pdf', 120, 'application/pdf');

    post(route('documents.replace-file', $document), [
        'file' => $newFile,
    ])->assertRedirect();

    $document->refresh();

    expect($document->id)->toBe($documentId)
        ->and($document->original_name)->toBe('updated.pdf')
        ->and($document->mime_type)->toBe('application/pdf')
        ->and($document->storage_path)->not->toBe($oldPath);

    Storage::assertMissing($oldPath);
    Storage::assertExists($document->storage_path);

    expect($project->fresh()->documents)->toHaveCount(1)
        ->and($project->documents->first()?->id)->toBe($documentId)
        ->and($task->fresh()->documents)->toHaveCount(1)
        ->and($task->documents->first()?->id)->toBe($documentId);
});
