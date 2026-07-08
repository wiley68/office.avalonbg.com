<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('profiler', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');
});

test('profiler is redirected from notes page to dashboard', function (): void {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    actingAs($profiler);

    get(route('dashboard.notes'))
        ->assertRedirect(route('dashboard'));
});

test('profiler receives forbidden when posting to dashboard agent', function (): void {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    actingAs($profiler);

    postJson('/dashboard/agent', ['message' => 'Hi'])
        ->assertForbidden();
});

test('admin can access notes page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    get(route('dashboard.notes'))
        ->assertOk();
});

test('office user can access notes page', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    get(route('dashboard.notes'))
        ->assertOk();
});
