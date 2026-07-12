<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('user', 'web');
});

test('office user can manage tasks within own project', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();
    $revision = $project->revisions()->create([
        'label' => 'v1.0',
        'description' => 'Initial',
        'sort_order' => 0,
    ]);

    actingAs($user);

    post(route('projects.tasks.store', $project), [
        'name' => 'Parent task',
        'description' => 'Top level',
        'status' => TaskStatus::Active->value,
        'parent_id' => null,
        'project_revision_id' => $revision->id,
    ])->assertRedirect();

    $parent = Task::query()->firstOrFail();

    post(route('projects.tasks.store', $project), [
        'name' => 'Child task',
        'description' => 'Depends on parent',
        'status' => TaskStatus::Active->value,
        'parent_id' => $parent->id,
        'project_revision_id' => $revision->id,
    ])->assertRedirect();

    $child = Task::query()->where('parent_id', $parent->id)->firstOrFail();

    patch(route('tasks.complete', $child))
        ->assertSessionHasErrors('status');

    patch(route('tasks.complete', $parent))->assertRedirect();

    patch(route('tasks.complete', $child))->assertRedirect();

    expect($child->fresh())
        ->status->toBe(TaskStatus::Completed)
        ->completed_at->not->toBeNull();

    put(route('tasks.update', $child), [
        'name' => 'Updated child task',
        'description' => 'Updated',
        'status' => TaskStatus::Deferred->value,
        'parent_id' => $parent->id,
        'project_revision_id' => $revision->id,
    ])->assertRedirect();

    expect($child->fresh())
        ->name->toBe('Updated child task')
        ->status->toBe(TaskStatus::Deferred)
        ->completed_at->toBeNull();

    delete(route('tasks.destroy', $child))->assertRedirect();
    delete(route('tasks.destroy', $parent))->assertRedirect();

    expect(Task::query()->count())->toBe(0);
});

test('office user can bulk reorder sibling tasks', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();

    actingAs($user);

    $first = $project->tasks()->create([
        'user_id' => $user->id,
        'name' => 'First',
        'status' => TaskStatus::Active,
        'sort_order' => 0,
    ]);

    $second = $project->tasks()->create([
        'user_id' => $user->id,
        'name' => 'Second',
        'status' => TaskStatus::Active,
        'sort_order' => 1,
    ]);

    patch(route('projects.tasks.reorder', $project), [
        'parent_id' => null,
        'task_ids' => [$second->id, $first->id],
    ])->assertRedirect();

    expect($second->fresh()->sort_order)->toBe(0);
    expect($first->fresh()->sort_order)->toBe(1);

    actingAs($user)
        ->getJson(route('internal.projects.tasks.index', $project))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Second')
        ->assertJsonPath('data.1.name', 'First');
});

test('office user cannot bulk reorder tasks from another users project', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');

    $other = User::factory()->create();
    $other->assignRole('user');

    $project = Project::factory()->for($owner)->create();

    $first = $project->tasks()->create([
        'user_id' => $owner->id,
        'name' => 'First',
        'status' => TaskStatus::Active,
        'sort_order' => 0,
    ]);

    $second = $project->tasks()->create([
        'user_id' => $owner->id,
        'name' => 'Second',
        'status' => TaskStatus::Active,
        'sort_order' => 1,
    ]);

    actingAs($other);

    patch(route('projects.tasks.reorder', $project), [
        'parent_id' => null,
        'task_ids' => [$second->id, $first->id],
    ])->assertNotFound();
});

test('office user can reorder a single task', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();

    actingAs($user);

    $first = $project->tasks()->create([
        'user_id' => $user->id,
        'name' => 'First',
        'status' => TaskStatus::Active,
        'sort_order' => 0,
    ]);

    $second = $project->tasks()->create([
        'user_id' => $user->id,
        'name' => 'Second',
        'status' => TaskStatus::Active,
        'sort_order' => 1,
    ]);

    patch(route('tasks.reorder', $first), [
        'parent_id' => null,
        'sort_order' => 5,
    ])->assertRedirect();

    expect($first->fresh()->sort_order)->toBe(5);

    actingAs($user)
        ->getJson(route('internal.projects.tasks.index', $project))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Second')
        ->assertJsonPath('data.1.name', 'First');
});

test('office user cannot manage tasks from another users project', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');

    $other = User::factory()->create();
    $other->assignRole('user');

    $project = Project::factory()->for($owner)->create();
    $task = Task::factory()->for($project)->for($owner)->create();

    actingAs($other);

    put(route('tasks.update', $task), [
        'name' => 'Hacked',
        'description' => null,
        'status' => TaskStatus::Active->value,
        'parent_id' => null,
        'project_revision_id' => null,
    ])->assertNotFound();
});
