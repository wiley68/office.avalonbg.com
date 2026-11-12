<?php

use App\Models\Access;
use App\Models\User;
use App\Services\AccessEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

test('office users have an automatically generated wrapped group key and cannot supply a user key', function (): void {
    $user = User::factory()->officeUser()->create();
    $this->actingAs($user);
    $encoded = Crypt::decryptString($user->group->getRawOriginal('access_encryption_key'));
    expect(strlen(base64_decode($encoded, true)))->toBe(32);
    $this->postJson('/settings/access-encryption-key', ['encryption_key' => 'ForbiddenKey123!'])->assertNotFound();
    expect($user->fresh()->access_encryption_key)->toBeNull();
});

test('access data can be encrypted and decrypted with the current group key', function (): void {
    $user = User::factory()->officeUser()->create();
    $this->actingAs($user);
    $service = app(AccessEncryptionService::class);
    $context = $service->context();
    $encrypted = $service->encryptData('plain-secret', $context);
    expect($encrypted)->not->toBe('plain-secret')->and($service->decryptData($encrypted, $context))->toBe('plain-secret');
});

test('office user can transform unsaved access data through the API without exposing a key', function (): void {
    $user = User::factory()->officeUser()->create();
    $this->actingAs($user);
    $state = accessFormState();
    $response = $this->postJson(route('internal.accesses.transform-data'), [...$state, 'action' => 'encrypt', 'content' => 'plain-secret', 'is_encrypted' => false])->assertOk();
    $this->postJson(route('internal.accesses.transform-data'), [...$state, 'action' => 'decrypt', 'content' => $response->json('content'), 'is_encrypted' => true])->assertOk()->assertJsonPath('content', 'plain-secret');
    expect(Access::query()->count())->toBe(0);
});

test('rotating the group key re-encrypts stored records without asking for replacement key material', function (): void {
    $user = User::factory()->officeUser()->create();
    $this->actingAs($user);
    $service = app(AccessEncryptionService::class);
    $state = accessFormState();
    $encrypted = $service->encryptData('credential-data', $state['encryption_context']);
    $record = Access::factory()->for($user)->create(['content' => $encrypted, 'is_encrypted' => true]);
    $this->put(route('access-encryption-key.rotate'), [...$state, 'current_password' => 'Password123!'])->assertSessionHasNoErrors()->assertRedirect();
    expect($record->fresh()->content)->not->toBe($encrypted)
        ->and($service->decryptData($record->fresh()->content, $service->context(), $record, $record->fresh()->version()))->toBe('credential-data');
});

test('transform API fails closed when the wrapped group key is unavailable', function (): void {
    $user = User::factory()->officeUser()->create();
    $user->group->forceFill(['access_encryption_key' => ''])->save();
    $this->actingAs($user)->postJson(route('internal.accesses.transform-data'), [...accessFormState(), 'action' => 'encrypt', 'content' => 'plain-secret', 'is_encrypted' => false])->assertUnprocessable()->assertJsonValidationErrors('content');
});
