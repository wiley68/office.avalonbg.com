<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('profiler', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');
});

test('admin can view users page with only user role accounts', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $officeUser = User::factory()->create();
    $officeUser->assignRole('user');

    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('admin');

    actingAs($admin);

    get('/users')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('users', 1)
            ->where('users.0.id', $officeUser->id));
});

test('profiler can view users page with only admin role accounts', function () {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    $managedAdmin = User::factory()->create();
    $managedAdmin->assignRole('admin');

    $officeUser = User::factory()->create();
    $officeUser->assignRole('user');

    actingAs($profiler);

    get('/users')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('users', 1)
            ->where('users.0.id', $managedAdmin->id));
});

test('office user cannot access users page', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    get('/users')
        ->assertForbidden();
});

test('admin can create user and verification email is sent', function () {
    Notification::fake();

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
        ->and($createdUser->hasRole('user'))->toBeTrue()
        ->and($createdUser->email_verified_at)->toBeNull();

    Notification::assertSentTo($createdUser, VerifyEmail::class);
});

test('profiler can create admin user', function () {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    actingAs($profiler);

    post('/users', [
        'name' => 'New Admin',
        'email' => 'new.admin@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])
        ->assertRedirect('/users');

    $createdAdmin = User::query()->where('email', 'new.admin@example.com')->first();

    expect($createdAdmin)->not->toBeNull()
        ->and($createdAdmin->hasRole('admin'))->toBeTrue();
});

test('admin cannot update user with admin role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('admin');

    actingAs($admin);

    put("/users/{$otherAdmin->id}", [
        'name' => 'Blocked',
        'email' => $otherAdmin->email,
        'password' => '',
        'password_confirmation' => '',
    ])->assertForbidden();
});

test('weak password is rejected when creating user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    post('/users', [
        'name' => 'New User',
        'email' => 'weak@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');
});

test('admin can update and delete user role account', function () {
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
