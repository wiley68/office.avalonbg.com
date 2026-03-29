<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');
});

test('admin is redirected from notes page to dashboard', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    get(route('dashboard.notes'))
        ->assertRedirect(route('dashboard'));
});

test('admin is redirected from warranties page to dashboard', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    get(route('dashboard.warranties'))
        ->assertRedirect(route('dashboard'));
});

test('admin receives forbidden when posting to dashboard agent', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    postJson('/dashboard/agent', ['message' => 'Hi'])
        ->assertForbidden();
});

test('non admin can access notes page', function (): void {
    $user = User::factory()->create();

    actingAs($user);

    get(route('dashboard.notes'))
        ->assertOk();
});

test('non admin can access warranties page', function (): void {
    $user = User::factory()->create();

    actingAs($user);

    get(route('dashboard.warranties'))
        ->assertOk();
});
