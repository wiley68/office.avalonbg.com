<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('user', 'web');
});

test('user without two factor is redirected to security settings', function (): void {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->assignRole('user');

    actingAs($user);

    get(route('dashboard'))
        ->assertRedirect(route('security.edit'));
});

test('user with two factor can access dashboard', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    get(route('dashboard'))
        ->assertOk();
});

test('user without two factor can access security settings', function (): void {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->assignRole('user');

    actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()]);

    get(route('security.edit'))
        ->assertOk();
});
