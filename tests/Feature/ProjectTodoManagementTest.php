<?php

use App\Enums\ProjectTodoStatus;
use App\Models\Project;
use App\Models\ProjectTodo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

test('office user can create update toggle and delete project todos', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();

    actingAs($user);

    post(route('projects.todos.store', $project), [
        'body' => 'Review client feedback before the next revision.',
    ])->assertRedirect();

    $todo = ProjectTodo::query()->first();

    expect($todo)
        ->not->toBeNull()
        ->and($todo->project_id)->toBe($project->id)
        ->and($todo->body)->toBe('Review client feedback before the next revision.')
        ->and($todo->status)->toBe(ProjectTodoStatus::Active)
        ->and($todo->completed_at)->toBeNull();

    put(route('projects.todos.update', [$project, $todo]), [
        'body' => 'Updated to-do text.',
    ])->assertRedirect();

    expect($todo->fresh()->body)->toBe('Updated to-do text.');

    patch(route('projects.todos.toggle', [$project, $todo]))
        ->assertRedirect();

    expect($todo->fresh())
        ->status->toBe(ProjectTodoStatus::Completed)
        ->completed_at->not->toBeNull();

    patch(route('projects.todos.toggle', [$project, $todo]))
        ->assertRedirect();

    expect($todo->fresh())
        ->status->toBe(ProjectTodoStatus::Active)
        ->completed_at->toBeNull();

    delete(route('projects.todos.destroy', [$project, $todo]))
        ->assertRedirect();

    expect(ProjectTodo::query()->count())->toBe(0);
});

test('project show includes todos ordered with active items first', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();

    $activeTodo = ProjectTodo::factory()->for($project)->create([
        'body' => 'Active item',
        'created_at' => now()->subDay(),
    ]);
    $completedTodo = ProjectTodo::factory()->for($project)->completed()->create([
        'body' => 'Completed item',
        'created_at' => now(),
    ]);

    actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Show')
            ->has('project.todos', 2)
            ->where('project.todos.0.id', $activeTodo->id)
            ->where('project.todos.0.status', ProjectTodoStatus::Active->value)
            ->where('project.todos.1.id', $completedTodo->id)
            ->where('project.todos.1.status', ProjectTodoStatus::Completed->value));
});

test('office user cannot manage todos on another users project', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');

    $other = User::factory()->create();
    $other->assignRole('user');

    $project = Project::factory()->for($owner)->create();
    $todo = ProjectTodo::factory()->for($project)->create();

    actingAs($other);

    post(route('projects.todos.store', $project), [
        'body' => 'Unauthorized to-do.',
    ])->assertNotFound();

    put(route('projects.todos.update', [$project, $todo]), [
        'body' => 'Hacked text.',
    ])->assertNotFound();

    patch(route('projects.todos.toggle', [$project, $todo]))
        ->assertNotFound();

    delete(route('projects.todos.destroy', [$project, $todo]))
        ->assertNotFound();
});

test('todo body validation rejects empty and overly long text', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();

    actingAs($user);

    post(route('projects.todos.store', $project), [
        'body' => '',
    ])->assertSessionHasErrors('body');

    post(route('projects.todos.store', $project), [
        'body' => str_repeat('a', 501),
    ])->assertSessionHasErrors('body');
});

test('todo from another project cannot be accessed through route binding', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->for($user)->create();
    $foreignTodo = ProjectTodo::factory()->for($otherProject)->create();

    actingAs($user);

    put(route('projects.todos.update', [$project, $foreignTodo]), [
        'body' => 'Should not work.',
    ])->assertNotFound();
});
