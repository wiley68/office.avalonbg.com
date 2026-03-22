<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.secret_key' => 'testing-secret-key-for-text-crypto']);
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');
});

test('guest cannot encrypt text', function (): void {
    postJson(route('dashboard.crypto.encrypt'), ['text' => 'hello'])
        ->assertUnauthorized();
});

test('admin cannot encrypt text', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    postJson(route('dashboard.crypto.encrypt'), ['text' => 'hello'])
        ->assertForbidden();
});

test('admin cannot decrypt text', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    postJson(route('dashboard.crypto.decrypt'), ['text' => 'abc'])
        ->assertForbidden();
});

test('standard user can encrypt and decrypt roundtrip', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    $response = postJson(route('dashboard.crypto.encrypt'), ['text' => 'secret message']);

    $response->assertOk();
    $cipher = $response->json('text');
    expect($cipher)->toBeString()->not->toBe('secret message');

    $round = postJson(route('dashboard.crypto.decrypt'), ['text' => $cipher]);

    $round->assertOk();
    expect($round->json('text'))->toBe('secret message');
});

test('standard user receives validation error for invalid decrypt payload', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    postJson(route('dashboard.crypto.decrypt'), ['text' => 'not-valid-ciphertext'])
        ->assertUnprocessable()
        ->assertJsonStructure(['message']);
});

test('decrypt accepts ciphertext with whitespace and line breaks', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    $response = postJson(route('dashboard.crypto.encrypt'), ['text' => 'plain text']);
    $cipher = $response->json('text');
    $withSpaces = chunk_split($cipher, 40, "\n ");

    $dec = postJson(route('dashboard.crypto.decrypt'), ['text' => $withSpaces]);
    $dec->assertOk();
    expect($dec->json('text'))->toBe('plain text');
});
