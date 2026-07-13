<?php

use App\Models\Project;
use App\Models\ProjectGitRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\getJson;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('user', 'web');
});

function fakeGitHubResponses(): void
{
    Http::fake([
        'api.github.com/repos/laravel/framework' => Http::response([
            'default_branch' => 'main',
            'open_issues_count' => 10,
            'stargazers_count' => 50000,
            'forks_count' => 15000,
            'pushed_at' => '2026-07-10T12:00:00Z',
        ]),
        'api.github.com/repos/laravel/framework/commits*' => Http::response([
            [
                'sha' => 'abc1234567890abcdef1234567890abcdef1234',
                'html_url' => 'https://github.com/laravel/framework/commit/abc1234567890abcdef1234567890abcdef1234',
                'commit' => [
                    'message' => "Improve routing\n\nAdditional details.",
                    'author' => [
                        'name' => 'Taylor Otwell',
                        'date' => '2026-07-10T11:00:00Z',
                    ],
                ],
            ],
        ]),
    ]);
}

test('office user can connect github repository to own project', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();

    actingAs($user);

    put(route('projects.git.upsert', $project), [
        'repository' => 'https://github.com/laravel/framework',
        'default_branch' => 'main',
    ])->assertRedirect();

    expect($project->fresh()->gitRepository)
        ->not->toBeNull()
        ->owner->toBe('laravel')
        ->repo->toBe('framework')
        ->default_branch->toBe('main');
});

test('office user cannot connect invalid github repository', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();

    actingAs($user);

    put(route('projects.git.upsert', $project), [
        'repository' => 'https://gitlab.com/group/project',
        'default_branch' => 'main',
    ])->assertSessionHasErrors('repository');

    expect($project->fresh()->gitRepository)->toBeNull();
});

test('office user can fetch github overview for own project', function () {
    fakeGitHubResponses();

    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();

    ProjectGitRepository::factory()->for($project)->create([
        'owner' => 'laravel',
        'repo' => 'framework',
        'default_branch' => 'main',
    ]);

    actingAs($user)
        ->getJson(route('internal.projects.git.show', $project))
        ->assertOk()
        ->assertJsonPath('data.repository.owner', 'laravel')
        ->assertJsonPath('data.repository.repo', 'framework')
        ->assertJsonPath('data.stats.stargazers_count', 50000)
        ->assertJsonPath('data.commits.data.0.short_sha', 'abc1234')
        ->assertJsonPath('data.commits.data.0.author_name', 'Taylor Otwell');
});

test('office user can remove github repository from own project', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $project = Project::factory()->for($user)->create();
    ProjectGitRepository::factory()->for($project)->create();

    actingAs($user);

    delete(route('projects.git.destroy', $project))->assertRedirect();

    expect($project->fresh()->gitRepository)->toBeNull();
});

test('office user cannot manage git settings for another users project', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');

    $other = User::factory()->create();
    $other->assignRole('user');

    $project = Project::factory()->for($owner)->create();

    actingAs($other);

    put(route('projects.git.upsert', $project), [
        'repository' => 'laravel/framework',
        'default_branch' => 'main',
    ])->assertNotFound();

    getJson(route('internal.projects.git.show', $project))
        ->assertNotFound();
});
