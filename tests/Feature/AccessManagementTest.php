<?php

use App\Models\Access;
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

test('office user can view accesses index page', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user)
        ->get(route('accesses.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page->component('accesses/Index'));
});

test('admin cannot access accesses pages', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin)
        ->get(route('accesses.index'))
        ->assertForbidden();
});

test('office user can create update and delete own access records', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    post(route('accesses.store'), [
        'name' => 'GitHub',
        'content' => 'secret-token',
        'is_encrypted' => false,
    ])->assertRedirect(route('accesses.index'));

    $access = Access::query()->first();

    expect($access)
        ->not->toBeNull()
        ->and($access->user_id)->toBe($user->id)
        ->and($access->name)->toBe('GitHub');

    put(route('accesses.update', $access), [
        'name' => 'GitLab',
        'content' => 'updated-token',
        'is_encrypted' => true,
    ])->assertRedirect(route('accesses.index'));

    expect($access->fresh())
        ->name->toBe('GitLab')
        ->is_encrypted->toBeTrue();

    delete(route('accesses.destroy', $access))
        ->assertRedirect(route('accesses.index'));

    expect(Access::query()->count())->toBe(0);
});

test('office user cannot manage another users access record', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');

    $other = User::factory()->create();
    $other->assignRole('user');

    $access = Access::factory()->for($owner)->create();

    actingAs($other);

    put(route('accesses.update', $access), [
        'name' => 'Hacked',
        'content' => 'x',
        'is_encrypted' => false,
    ])->assertForbidden();

    delete(route('accesses.destroy', $access))
        ->assertForbidden();
});

test('accesses api returns only current user records', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    Access::factory()->for($user)->count(2)->create();

    $other = User::factory()->create();
    $other->assignRole('user');
    Access::factory()->for($other)->create();

    actingAs($user)
        ->getJson(route('internal.accesses.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});
