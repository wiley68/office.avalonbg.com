<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');
});

test('admin can view users page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    get('/users')
        ->assertSuccessful();
});

test('non admin cannot access users page', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    get('/users')
        ->assertForbidden();
});

test('admin can create user and new user gets user role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    post('/users', [
        'name' => 'New User',
        'email' => 'new.user@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])
        ->assertRedirect('/users');

    $createdUser = User::query()->where('email', 'new.user@example.com')->first();

    expect($createdUser)->not->toBeNull()
        ->and($createdUser->hasRole('user'))->toBeTrue();
});

test('admin can update and delete user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $managedUser = User::factory()->create();
    $managedUser->assignRole('user');

    actingAs($admin);

    put("/users/{$managedUser->id}", [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'password' => '',
        'password_confirmation' => '',
    ])
        ->assertRedirect('/users');

    expect($managedUser->refresh()->name)->toBe('Updated Name')
        ->and($managedUser->email)->toBe('updated@example.com');

    delete("/users/{$managedUser->id}")
        ->assertRedirect('/users');

    expect(User::query()->whereKey($managedUser->id)->exists())->toBeFalse();
});
