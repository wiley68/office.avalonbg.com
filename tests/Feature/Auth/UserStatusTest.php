<?php

use App\Models\User;
use App\Support\Translations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('profiler', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');
});

test('inactive users cannot authenticate', function () {
    $user = User::factory()->withoutTwoFactor()->inactive()->create();

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'Password123!',
    ])
        ->assertSessionHasErrors('email')
        ->assertRedirect();

    assertGuest();

    expect(session('errors')->get('email')[0])
        ->toBe(Translations::get('auth.inactive'));
});

test('active users can authenticate', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'status' => 1,
    ]);

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'Password123!',
    ])->assertRedirect(route('dashboard', absolute: false));
});

test('deactivated authenticated user is logged out and redirected to login', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    actingAs($user)
        ->get(route('users.index'))
        ->assertOk();

    $user->update(['status' => 0]);

    get(route('users.index'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    assertGuest();
});

test('admin can deactivate user on update', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $managedUser = User::factory()->create();
    $managedUser->assignRole('user');

    actingAs($admin)
        ->put("/users/{$managedUser->id}", [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'status' => 0,
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect('/users');

    expect($managedUser->refresh()->status)->toBe(0)
        ->and($managedUser->isActive())->toBeFalse();
});

test('new users are active by default when created by admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    post('/users', [
        'name' => 'New User',
        'email' => 'active.user@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'status' => 1,
    ])->assertRedirect('/users');

    $createdUser = User::query()->where('email', 'active.user@example.com')->first();

    expect($createdUser)->not->toBeNull()
        ->and($createdUser->status)->toBe(1)
        ->and($createdUser->isActive())->toBeTrue();
});

test('edit page exposes user status', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $managedUser = User::factory()->inactive()->create();
    $managedUser->assignRole('user');

    actingAs($admin)
        ->get("/users/{$managedUser->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('user.status', 0));
});
