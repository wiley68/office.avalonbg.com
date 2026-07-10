<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('profiler', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');
});

test('creating a user requires password change on first login', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    post('/users', [
        'name' => 'New User',
        'email' => 'new.user@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertRedirect('/users');

    $createdUser = User::query()->where('email', 'new.user@example.com')->first();

    expect($createdUser)->not->toBeNull()
        ->and($createdUser->must_change_password)->toBeTrue();
});

test('user with temporary password is redirected to required password change page on login', function () {
    $user = User::factory()->withoutTwoFactor()->mustChangePassword()->create();

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'Password123!',
    ])->assertRedirect(route('password.change.edit', absolute: false));

    assertAuthenticated();
});

test('user with temporary password cannot access dashboard until password is changed', function () {
    $user = User::factory()->withoutTwoFactor()->mustChangePassword()->create();

    actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('password.change.edit'));
});

test('user can change required password and is redirected to security settings without two factor', function () {
    $user = User::factory()->withoutTwoFactor()->mustChangePassword()->create();

    actingAs($user)
        ->put(route('password.change.update'), [
            'current_password' => 'Password123!',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
        ->assertRedirect(route('security.edit'));

    expect($user->refresh()->must_change_password)->toBeFalse();
});

test('user with changed password and two factor enabled can access dashboard', function () {
    $user = User::factory()->mustChangePassword()->create();

    actingAs($user)
        ->put(route('password.change.update'), [
            'current_password' => 'Password123!',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
        ->assertRedirect(route('dashboard'));

    expect($user->refresh()->must_change_password)->toBeFalse();

    actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('required password change rejects the same password as current', function () {
    $user = User::factory()->withoutTwoFactor()->mustChangePassword()->create();

    actingAs($user)
        ->from(route('password.change.edit'))
        ->put(route('password.change.update'), [
            'current_password' => 'Password123!',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertRedirect(route('password.change.edit'))
        ->assertSessionHasErrors('password');

    expect($user->refresh()->must_change_password)->toBeTrue();
});

test('admin password reset requires user to change password on next login', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $managedUser = User::factory()->create();
    $managedUser->assignRole('user');

    actingAs($admin)
        ->put("/users/{$managedUser->id}", [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'status' => 1,
            'password' => 'ResetPass1!',
            'password_confirmation' => 'ResetPass1!',
        ])
        ->assertRedirect(route('users.edit', $managedUser));

    expect($managedUser->refresh()->must_change_password)->toBeTrue();
});

test('users without required password change can access required password change page redirect', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    actingAs($user)
        ->get(route('password.change.edit'))
        ->assertRedirect(route('dashboard'));
});
