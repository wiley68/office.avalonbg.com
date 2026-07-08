<?php

use App\Models\Note;
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

test('guest cannot access admin export page', function (): void {
    get(route('dashboard.admin.export'))->assertRedirect();
});

test('profiler cannot access admin export page', function (): void {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    actingAs($profiler);

    get(route('dashboard.admin.export'))->assertForbidden();
});

test('office user can view export page', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    get(route('dashboard.admin.export'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/DataExport'));
});

test('admin can view export page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    get(route('dashboard.admin.export'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/DataExport'));
});

test('admin can download notes export', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $owner = User::factory()->create();
    $owner->assignRole('user');
    Note::factory()->for($owner)->create();

    actingAs($admin);

    get(route('dashboard.admin.export.notes'))
        ->assertOk()
        ->assertHeaderContains('content-type', 'spreadsheetml');
});

test('office user can download notes export', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    Note::factory()->for($user)->create();

    actingAs($user);

    get(route('dashboard.admin.export.notes'))
        ->assertOk()
        ->assertHeaderContains('content-type', 'spreadsheetml');
});
