<?php

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectRevision;
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
        ->assertJsonCount(2, 'data');
});

test('office user can manage project revisions', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();

    actingAs($user);

    post(route('projects.revisions.store', $project), [
        'label' => 'v1.0',
        'description' => 'Initial release',
        'sort_order' => 0,
    ])->assertRedirect();

    $revision = ProjectRevision::query()->firstOrFail();

    expect($revision->label)->toBe('v1.0');

    put(route('projects.revisions.update', [$project, $revision]), [
        'label' => 'v1.1',
        'description' => 'Bug fixes',
        'sort_order' => 1,
    ])->assertRedirect();

    expect($revision->fresh()->label)->toBe('v1.1');

    delete(route('projects.revisions.destroy', [$project, $revision]))
        ->assertRedirect();

    expect(ProjectRevision::query()->count())->toBe(0);
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
