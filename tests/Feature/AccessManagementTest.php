<?php

use App\Models\Access;
use App\Models\User;
use App\Services\AccessEncryptionService;
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
    $user = User::factory()->officeUser()->create();

    actingAs($user)
        ->get(route('accesses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('accesses/Index'));
});

test('admin cannot access accesses pages', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin)
        ->get(route('accesses.index'))
        ->assertForbidden();
});

test('office user can create update and delete own access records', function () {
    $user = User::factory()->officeUser()->create();

    actingAs($user);

    post(route('accesses.store'), [
        ...accessFormState(),
        'name' => 'GitHub',
        'category' => 'Work',
        'content' => 'secret-token',
        'is_encrypted' => false,
    ])->assertRedirect(route('accesses.index'));

    $access = Access::query()->first();

    expect($access)
        ->not->toBeNull()
        ->and($access->user_id)->toBe($user->id)
        ->and($access->name)->toBe('GitHub')
        ->and($access->category)->toBe('Work');

    put(route('accesses.update', $access), [
        ...accessFormState($access),
        'name' => 'GitLab',
        'category' => 'Personal',
        'content' => app(AccessEncryptionService::class)->encryptData('updated-token', accessFormState()['encryption_context'], $access, $access->version()),
        'is_encrypted' => true,
    ])->assertRedirect(route('accesses.index'));

    expect($access->fresh())
        ->name->toBe('GitLab')
        ->category->toBe('Personal')
        ->is_encrypted->toBeTrue();

    delete(route('accesses.destroy', $access), accessFormState($access->fresh()))
        ->assertRedirect(route('accesses.index'));

    expect(Access::query()->count())->toBe(0);
});

test('office user cannot manage another users access record', function () {
    $owner = User::factory()->officeUser()->create();

    $other = User::factory()->officeUser()->create();

    $access = Access::factory()->for($owner)->create();

    actingAs($other);

    put(route('accesses.update', $access), [
        ...accessFormState($access),
        'name' => 'Hacked',
        'category' => null,
        'content' => 'x',
        'is_encrypted' => false,
    ])->assertNotFound();

    delete(route('accesses.destroy', $access))
        ->assertNotFound();
});

test('accesses api returns only current user records', function () {
    $user = User::factory()->officeUser()->create();

    Access::factory()->for($user)->count(2)->create();

    $other = User::factory()->officeUser()->create();
    Access::factory()->for($other)->create();

    actingAs($user)
        ->getJson(route('internal.accesses.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});
