<?php

use App\Models\Access;
use App\Models\User;
use App\Services\AccessEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('user', 'web');
});

test('office user can store access encryption key in security settings', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user)
        ->from(route('security.edit'))
        ->post(route('access-encryption-key.store'), [
            'encryption_key' => 'MySecretKey123!',
            'encryption_key_confirmation' => 'MySecretKey123!',
        ])
        ->assertRedirect(route('security.edit'));

    expect(app(AccessEncryptionService::class)->hasStoredKey($user->fresh()))->toBeTrue();
});

test('access data can be encrypted and decrypted with user key', function () {
    $service = app(AccessEncryptionService::class);
    $user = User::factory()->create();
    $user->assignRole('user');

    $service->storeKey($user, 'MySecretKey123!');

    $encrypted = $service->encryptData('plain-secret', 'MySecretKey123!');

    expect($encrypted)->not->toBe('plain-secret')
        ->and($service->decryptData($encrypted, 'MySecretKey123!'))->toBe('plain-secret');
});

test('office user can transform access data through api', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    app(AccessEncryptionService::class)->storeKey($user, 'MySecretKey123!');

    actingAs($user);

    $encryptedResponse = postJson(route('internal.accesses.transform-data'), [
        'action' => 'encrypt',
        'content' => 'plain-secret',
        'is_encrypted' => false,
    ])->assertOk();

    $encryptedPayload = $encryptedResponse->json('content');

    postJson(route('internal.accesses.transform-data'), [
        'action' => 'decrypt',
        'content' => $encryptedPayload,
        'is_encrypted' => true,
    ])
        ->assertOk()
        ->assertJson([
            'content' => 'plain-secret',
            'is_encrypted' => false,
        ]);
});

test('rotating encryption key re-encrypts stored access records', function () {
    $service = app(AccessEncryptionService::class);
    $user = User::factory()->create();
    $user->assignRole('user');

    $service->storeKey($user, 'OldSecretKey123!');
    $encrypted = $service->encryptData('credential-data', 'OldSecretKey123!');

    Access::factory()->for($user)->create([
        'content' => $encrypted,
        'is_encrypted' => true,
    ]);

    actingAs($user)
        ->from(route('security.edit'))
        ->put(route('access-encryption-key.rotate'), [
            'current_password' => 'Password123!',
            'encryption_key' => 'NewSecretKey456!',
            'encryption_key_confirmation' => 'NewSecretKey456!',
        ])
        ->assertRedirect(route('security.edit'));

    $access = Access::query()->firstOrFail();
    $newKey = $service->resolveKey($user->fresh());

    expect($newKey)->toBe('NewSecretKey456!')
        ->and($service->decryptData($access->content, $newKey))->toBe('credential-data');
});

test('transform api requires configured encryption key', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user)
        ->postJson(route('internal.accesses.transform-data'), [
            'action' => 'encrypt',
            'content' => 'plain-secret',
            'is_encrypted' => false,
        ])
        ->assertStatus(422);
});
