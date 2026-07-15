<?php

use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectEmailAttachment;
use App\Models\ProjectEmailLink;
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

test('documents api can store a document and return its id', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user)
        ->post(route('internal.documents.store'), [
            'file' => UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf'),
            'description' => 'Task attachment',
        ])
        ->assertCreated()
        ->assertJsonPath('data.original_name', 'spec.pdf')
        ->assertJsonPath('data.mime_type', 'application/pdf')
        ->assertJsonStructure(['data' => ['id', 'original_name', 'mime_type', 'size_bytes', 'created_at']]);

    expect(Document::query()->where('user_id', $user->id)->count())->toBe(1);
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

test('office user can upload svg documents', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    $file = UploadedFile::fake()->createWithContent(
        'logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>',
    );

    post(route('documents.store'), [
        'file' => $file,
        'description' => 'Vector logo',
    ])->assertRedirect();

    $document = Document::query()->firstOrFail();

    expect($document)
        ->original_name->toBe('logo.svg')
        ->and($document->mime_type)->toBe('image/svg+xml');

    actingAs($user)
        ->get(route('documents.download', $document))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Disposition', 'inline; filename="logo.svg"; filename*=UTF-8\'\'logo.svg');
});

test('office user can upload txt file reported as octet-stream', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    $file = UploadedFile::fake()->create('notes.txt', 1, 'application/octet-stream');

    post(route('documents.store'), [
        'file' => $file,
        'description' => 'Plain notes',
    ])->assertRedirect();

    $document = Document::query()->firstOrFail();

    expect($document)
        ->original_name->toBe('notes.txt')
        ->and($document->mime_type)->toBe('text/plain');
});

test('office user cannot delete document used by archived project email attachment', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create([
        'name' => 'Website redesign',
    ]);

    $document = Document::factory()->for($user)->create([
        'original_name' => 'specification.pdf',
    ]);

    Storage::disk('local')->put($document->storage_path, '%PDF-1.4');

    $emailLink = ProjectEmailLink::factory()->for($project)->for($user)->archived()->create([
        'subject' => 'Re: DEM-18992',
    ]);

    ProjectEmailAttachment::query()->create([
        'project_email_link_id' => $emailLink->id,
        'imap_part' => '2',
        'document_id' => $document->id,
    ]);

    actingAs($user);

    delete(route('documents.destroy', $document))
        ->assertSessionHasErrors('document');

    expect(Document::query()->whereKey($document->id)->exists())->toBeTrue();
    expect(session('errors')->get('document')[0])
        ->toContain('Website redesign')
        ->toContain('DEM-18992');
});
