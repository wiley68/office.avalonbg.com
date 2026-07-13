<?php

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectRevision;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('profiler', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');
});

test('office user can view projects index page', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('projects/Index'));
});

test('admin cannot access projects pages', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin)
        ->get(route('projects.index'))
        ->assertForbidden();
});

test('office user can create update and delete own projects', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    post(route('projects.store'), [
        'name' => 'Website redesign',
        'description' => 'Refresh the public website.',
        'status' => ProjectStatus::Active->value,
        'expected_completion_at' => '2026-12-31',
    ])->assertRedirect(route('projects.index'));

    $project = Project::query()->first();

    expect($project)
        ->not->toBeNull()
        ->and($project->user_id)->toBe($user->id)
        ->and($project->name)->toBe('Website redesign');

    put(route('projects.update', $project), [
        'name' => 'Website relaunch',
        'description' => 'Updated scope.',
        'status' => ProjectStatus::Completed->value,
        'expected_completion_at' => '2026-12-31',
    ])->assertRedirect(route('projects.show', $project));

    expect($project->fresh())
        ->name->toBe('Website relaunch')
        ->status->toBe(ProjectStatus::Completed)
        ->completed_at->not->toBeNull();

    delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'));

    expect(Project::query()->count())->toBe(0);
});

test('office user cannot manage another users project', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');

    $other = User::factory()->create();
    $other->assignRole('user');

    $project = Project::factory()->for($owner)->create();

    actingAs($other);

    put(route('projects.update', $project), [
        'name' => 'Hacked',
        'description' => null,
        'status' => ProjectStatus::Active->value,
        'expected_completion_at' => null,
    ])->assertNotFound();

    delete(route('projects.destroy', $project))
        ->assertNotFound();
});

test('projects api returns only current user records', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    Project::factory()->for($user)->count(2)->create();

    $other = User::factory()->create();
    $other->assignRole('user');
    Project::factory()->for($other)->create();

    actingAs($user)
        ->getJson(route('internal.projects.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.documents_count', 0)
        ->assertJsonPath('data.0.tasks_count', 0);
});

test('projects api includes project document count excluding task attachments', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();
    $document = Document::factory()->for($user)->create();

    $project->documents()->attach($document);

    actingAs($user)
        ->getJson(route('internal.projects.index'))
        ->assertOk()
        ->assertJsonPath('data.0.documents_count', 1);
});

test('projects api includes latest revision label', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();

    ProjectRevision::factory()->for($project)->create([
        'label' => 'v1.0',
        'sort_order' => 0,
    ]);
    ProjectRevision::factory()->for($project)->create([
        'label' => 'v2.0',
        'sort_order' => 2,
    ]);
    ProjectRevision::factory()->for($project)->create([
        'label' => 'v1.5',
        'sort_order' => 1,
    ]);

    actingAs($user)
        ->getJson(route('internal.projects.index'))
        ->assertOk()
        ->assertJsonPath('data.0.latest_revision.label', 'v2.0');

    $projectWithoutRevisions = Project::factory()->for($user)->create();

    actingAs($user)
        ->getJson(route('internal.projects.index', ['search' => (string) $projectWithoutRevisions->id]))
        ->assertOk()
        ->assertJsonPath('data.0.latest_revision', null);
});

test('projects api includes task count', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();

    Task::factory()->for($project)->for($user)->count(2)->create();

    actingAs($user)
        ->getJson(route('internal.projects.index', ['search' => (string) $project->id]))
        ->assertOk()
        ->assertJsonPath('data.0.tasks_count', 2);
});

test('tasks api includes mime type for attached documents', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->for($project)->for($user)->create();
    $document = Document::factory()->for($user)->create([
        'mime_type' => 'application/pdf',
        'original_name' => 'spec.pdf',
    ]);

    $task->documents()->attach($document);

    actingAs($user)
        ->getJson(route('internal.projects.tasks.index', $project))
        ->assertOk()
        ->assertJsonPath('data.0.documents.0.id', $document->id)
        ->assertJsonPath('data.0.documents.0.original_name', 'spec.pdf')
        ->assertJsonPath('data.0.documents.0.mime_type', 'application/pdf');
});

test('project show includes mime type for attached documents', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();
    $mimeType = 'text/plain';
    $fileName = 'notes.txt';
    $document = Document::factory()->for($user)->create([
        'mime_type' => $mimeType,
        'original_name' => $fileName,
    ]);

    $project->documents()->attach($document);

    actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Show')
            ->where('project.documents.0.mime_type', $mimeType)
            ->where('project.documents.0.original_name', $fileName));
});

test('office user can manage project revisions', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();

    actingAs($user);

    post(route('projects.revisions.store', $project), [
        'label' => 'v1.0',
        'description' => 'Initial release',
    ])->assertRedirect();

    $revision = ProjectRevision::query()->firstOrFail();

    expect($revision->label)->toBe('v1.0')
        ->and($revision->sort_order)->toBe(0);

    post(route('projects.revisions.store', $project), [
        'label' => 'v2.0',
        'description' => 'Second release',
    ])->assertRedirect();

    $newestRevision = ProjectRevision::query()
        ->where('label', 'v2.0')
        ->firstOrFail();

    expect($newestRevision->sort_order)->toBe(1);

    put(route('projects.revisions.update', [$project, $revision]), [
        'label' => 'v1.1',
        'description' => 'Bug fixes',
    ])->assertRedirect();

    expect($revision->fresh()->label)->toBe('v1.1');

    delete(route('projects.revisions.destroy', [$project, $revision]))
        ->assertRedirect();

    expect(ProjectRevision::query()->count())->toBe(1);
});

test('office user can reorder project revisions with newest at top', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();

    $v1 = ProjectRevision::factory()->for($project)->create([
        'label' => 'v1.0',
        'sort_order' => 0,
    ]);
    $v2 = ProjectRevision::factory()->for($project)->create([
        'label' => 'v2.0',
        'sort_order' => 1,
    ]);
    $v3 = ProjectRevision::factory()->for($project)->create([
        'label' => 'v3.0',
        'sort_order' => 2,
    ]);

    actingAs($user)
        ->patch(route('projects.revisions.reorder', $project), [
            'revision_ids' => [$v1->id, $v3->id, $v2->id],
        ])
        ->assertRedirect();

    expect($v1->fresh()->sort_order)->toBe(2)
        ->and($v3->fresh()->sort_order)->toBe(1)
        ->and($v2->fresh()->sort_order)->toBe(0);

    actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/Show')
            ->where('project.revisions.0.label', 'v1.0')
            ->where('project.revisions.1.label', 'v3.0')
            ->where('project.revisions.2.label', 'v2.0'));
});

test('completing project sets completed_at and clearing status removes it', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create([
        'status' => ProjectStatus::Active,
        'completed_at' => null,
    ]);

    actingAs($user);

    put(route('projects.update', $project), [
        'name' => $project->name,
        'description' => $project->description,
        'status' => ProjectStatus::Completed->value,
        'expected_completion_at' => null,
    ])->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->completed_at)->not->toBeNull();

    put(route('projects.update', $project), [
        'name' => $project->name,
        'description' => $project->description,
        'status' => ProjectStatus::Active->value,
        'expected_completion_at' => null,
    ])->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->completed_at)->toBeNull();
});

test('project cannot be completed while tasks remain open', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();

    $project->tasks()->create([
        'user_id' => $user->id,
        'name' => 'Open task',
        'status' => TaskStatus::Active,
        'sort_order' => 0,
    ]);

    actingAs($user);

    put(route('projects.update', $project), [
        'name' => $project->name,
        'description' => $project->description,
        'status' => ProjectStatus::Completed->value,
        'expected_completion_at' => null,
    ])->assertSessionHasErrors('status');
});
