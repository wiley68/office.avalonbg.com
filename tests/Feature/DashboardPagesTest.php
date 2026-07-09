<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('profiler', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');
});

test('dashboard page can be rendered', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
});

test('composer page can be rendered for office users', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    get(route('dashboard.composer'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('office/Composer'));
});

test('profiler is redirected from composer page to dashboard', function (): void {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    actingAs($profiler);

    get(route('dashboard.composer'))
        ->assertRedirect(route('dashboard'));
});
