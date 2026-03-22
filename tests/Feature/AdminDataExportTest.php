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
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');
});

test('guest cannot access admin export page', function (): void {
    get(route('dashboard.admin.export'))->assertRedirect();
});

test('non admin cannot access admin export page', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    get(route('dashboard.admin.export'))->assertForbidden();
});

test('admin can view export page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    get(route('dashboard.admin.export'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/DataExport'));
});

test('admin can download notes xlsx export', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $owner = User::factory()->create();
    Note::factory()->for($owner)->create([
        'name' => 'N1',
        'note' => 'Съдържание',
    ]);

    actingAs($admin);

    $response = get(route('dashboard.admin.export.notes'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain(
        'spreadsheetml',
    );
});

test('non admin cannot download notes xlsx export', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    get(route('dashboard.admin.export.notes'))->assertForbidden();
});
