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
