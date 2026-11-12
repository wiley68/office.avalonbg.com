<?php

use App\Models\Access;
use App\Models\Group;
use App\Models\User;
use App\Policies\AccessPolicy;
use App\Services\AccessEncryptionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->creator = User::factory()->officeUser()->create();
    $this->peer = User::factory()->officeUser($this->creator->group)->create();
    $this->outsider = User::factory()->officeUser()->create();
    $this->actingAs($this->creator);
    $this->service = app(AccessEncryptionService::class);
});

/** @return array<string, mixed> */
function encryptedAccessForm(string $content, ?Access $record = null): array
{
    return [...accessFormState($record), 'name' => 'Encrypted access', 'category' => 'Work', 'content' => $content, 'is_encrypted' => true];
}

test('group keys are independent random wrapped 32-byte keys and AES uses the exact decoded bytes', function (): void {
    $group = $this->creator->group;
    $encoded = Crypt::decryptString($group->getRawOriginal('access_encryption_key'));
    $key = base64_decode($encoded, true);
    $otherKey = base64_decode(Crypt::decryptString($this->outsider->group->getRawOriginal('access_encryption_key')), true);
    expect(strlen($key))->toBe(32)->and($key === $otherKey)->toBeFalse();
    $context = $this->service->context();
    $first = $this->service->encryptData('secret text', $context);
    $second = $this->service->encryptData('secret text', $context);
    $raw = base64_decode($first, true);
    expect(strlen($raw))->toBe(28 + strlen('secret text'))
        ->and(substr($raw, 0, 12) === substr(base64_decode($second, true), 0, 12))->toBeFalse()
        ->and(openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16)))->toBe('secret text')
        ->and(openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16)))->toBeFalse();
    expect(array_key_exists('access_encryption_key', $group->toArray()))->toBeFalse();
});

test('same-group encrypted HTTP create read transform edit and delete preserve the creator', function (): void {
    $context = $this->service->context();
    $encrypted = $this->postJson(route('internal.accesses.transform-data'), [...accessFormState(), 'action' => 'encrypt', 'content' => 'original secret', 'is_encrypted' => false])->assertOk()->json('content');
    $this->post(route('accesses.store'), [...encryptedAccessForm($encrypted), 'group_id' => $this->outsider->group_id, 'user_id' => $this->peer->id])->assertSessionHasNoErrors()->assertRedirect();
    $record = Access::query()->sole();
    $this->actingAs($this->peer)->getJson(route('internal.accesses.index'))->assertOk()->assertJsonPath('data.0.content', $encrypted)->assertJsonPath('data.0.version', $record->version());
    $this->postJson(route('internal.accesses.transform-data'), [...accessFormState($record), 'access_id' => $record->id, 'action' => 'decrypt', 'content' => $encrypted, 'is_encrypted' => true])->assertOk()->assertJsonPath('content', 'original secret');
    $replacement = $this->service->encryptData('peer secret', $context, $record, $record->version());
    $this->put(route('accesses.update', $record), [...encryptedAccessForm($replacement, $record), 'user_id' => $this->peer->id, 'group_id' => $this->outsider->group_id])->assertSessionHasNoErrors()->assertRedirect();
    $fresh = $record->fresh();
    expect($fresh->user_id)->toBe($this->creator->id)->and($fresh->group_id)->toBe($this->creator->group_id)
        ->and($this->service->decryptData($fresh->content, $context, $fresh, $fresh->version()))->toBe('peer secret');
    $this->delete(route('accesses.destroy', $record), accessFormState($fresh))->assertSessionHasNoErrors()->assertRedirect();
    expect($record->fresh())->toBeNull();
});

test('creator and peer repeatedly edit through the transform API and decrypt exactly once after each save', function (): void {
    $state = accessFormState();
    $plaintext = 'Initial synthetic access';
    $encrypted = $this->postJson(route('internal.accesses.transform-data'), [
        ...$state, 'action' => 'encrypt', 'content' => $plaintext, 'is_encrypted' => false,
    ])->assertOk()->assertJsonPath('is_encrypted', true)->json('content');
    $this->post(route('accesses.store'), encryptedAccessForm($encrypted))->assertSessionHasNoErrors()->assertRedirect();
    $record = Access::query()->sole();

    foreach ([$this->peer, $this->creator, $this->peer, $this->creator] as $iteration => $editor) {
        $this->actingAs($editor);
        $row = $this->getJson(route('internal.accesses.index'))->assertOk()->json('data.0');
        $form = ['access_id' => $record->id, 'encryption_context' => $row['encryption_context'], 'version' => $row['version']];
        $this->postJson(route('internal.accesses.transform-data'), [
            ...$form, 'action' => 'decrypt', 'content' => $row['content'], 'is_encrypted' => true,
        ])->assertOk()->assertJsonPath('content', $plaintext)->assertJsonPath('is_encrypted', false);

        $plaintext = 'Edited synthetic access '.$iteration.' — проверка';
        $replacement = $this->postJson(route('internal.accesses.transform-data'), [
            ...$form, 'action' => 'encrypt', 'content' => $plaintext, 'is_encrypted' => false,
        ])->assertOk()->assertJsonPath('is_encrypted', true)->json('content');
        $this->postJson(route('internal.accesses.transform-data'), [
            ...$form, 'action' => 'encrypt', 'content' => $replacement, 'is_encrypted' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_encrypted');
        $this->put(route('accesses.update', $record), encryptedAccessForm($replacement, $record->fresh()))->assertSessionHasNoErrors()->assertRedirect();

        $fresh = $record->fresh();
        expect($fresh->user_id)->toBe($this->creator->id)
            ->and($fresh->group_id)->toBe($this->creator->group_id)
            ->and($fresh->is_encrypted)->toBeTrue();
        $this->actingAs($this->creator)->postJson(route('internal.accesses.transform-data'), [
            ...accessFormState($fresh), 'access_id' => $fresh->id, 'action' => 'decrypt', 'content' => $fresh->content, 'is_encrypted' => true,
        ])->assertOk()->assertJsonPath('content', $plaintext)->assertJsonPath('is_encrypted', false);
    }
});

test('transform API rejects inconsistent or invalid form states', function (array $attributes, string $field): void {
    $this->postJson(route('internal.accesses.transform-data'), [
        ...accessFormState(), 'content' => 'Synthetic fixture', ...$attributes,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(Access::query()->count())->toBe(0);
})->with([
    'already encrypted' => [['action' => 'encrypt', 'is_encrypted' => true], 'is_encrypted'],
    'already encrypted integer' => [['action' => 'encrypt', 'is_encrypted' => 1], 'is_encrypted'],
    'already plaintext' => [['action' => 'decrypt', 'is_encrypted' => false], 'content'],
    'missing state' => [['action' => 'encrypt'], 'is_encrypted'],
    'invalid state' => [['action' => 'encrypt', 'is_encrypted' => 'invalid'], 'is_encrypted'],
    'invalid action' => [['action' => 'invalid', 'is_encrypted' => false], 'action'],
    'unauthenticated ciphertext' => [['action' => 'decrypt', 'is_encrypted' => true], 'content'],
]);

test('valid editor transitions still require the current persisted record version and encryption context', function (): void {
    $ciphertext = $this->service->encryptData('Synthetic fixture', $this->service->context());
    $record = $this->service->create(encryptedAccessForm($ciphertext));
    $this->actingAs($this->peer);
    foreach (['encrypt', 'decrypt'] as $action) {
        $form = [...accessFormState($record), 'access_id' => $record->id, 'action' => $action, 'content' => $action === 'encrypt' ? 'Edited fixture' : $ciphertext, 'is_encrypted' => $action === 'decrypt'];
        foreach (['version', 'encryption_context'] as $field) {
            $this->postJson(route('internal.accesses.transform-data'), [...$form, $field => str_repeat('0', 64)])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
    expect($record->fresh()->content)->toBe($ciphertext)->and($record->fresh()->version())->toBe($record->version());
});

test('an encrypted record requires explicit confirmation before a peer can save plaintext', function (): void {
    $ciphertext = $this->service->encryptData('Synthetic fixture', $this->service->context());
    $record = $this->service->create(encryptedAccessForm($ciphertext));
    $this->actingAs($this->peer);
    $form = [...encryptedAccessForm('Intentionally plain fixture', $record), 'is_encrypted' => false];
    foreach ([[], ['remove_encryption' => false], ['remove_encryption' => 'invalid']] as $confirmation) {
        $this->putJson(route('accesses.update', $record), [...$form, ...$confirmation])->assertUnprocessable()->assertJsonValidationErrors('remove_encryption');
        expect($record->fresh()->content)->toBe($ciphertext)->and($record->fresh()->is_encrypted)->toBeTrue();
    }
    $this->put(route('accesses.update', $record), [...$form, 'remove_encryption' => true])->assertSessionHasNoErrors()->assertRedirect();
    $fresh = $record->fresh();
    expect($fresh->content)->toBe('Intentionally plain fixture')->and($fresh->is_encrypted)->toBeFalse()
        ->and($fresh->user_id)->toBe($this->creator->id)->and($fresh->group_id)->toBe($this->creator->group_id);
    $this->put(route('accesses.update', $record), [...$form, ...accessFormState($fresh), 'content' => 'Plain edit remains supported'])->assertSessionHasNoErrors()->assertRedirect();
    expect($record->fresh()->content)->toBe('Plain edit remains supported');
});

test('cross-group raw ciphertext and persisted IDs cannot reveal plaintext or mutate records', function (): void {
    $context = $this->service->context();
    $ciphertext = $this->service->encryptData('private group content', $context);
    $record = $this->service->create(encryptedAccessForm($ciphertext));
    $this->actingAs($this->outsider);
    $otherState = accessFormState();
    $this->getJson(route('internal.accesses.index'))->assertOk()->assertJsonPath('total', 0);
    $this->postJson(route('internal.accesses.transform-data'), [...$otherState, 'action' => 'decrypt', 'content' => $ciphertext, 'is_encrypted' => true])->assertUnprocessable()->assertJsonValidationErrors('content');
    $this->postJson(route('internal.accesses.transform-data'), [...$otherState, 'access_id' => $record->id, 'version' => $record->version(), 'action' => 'decrypt', 'content' => $ciphertext, 'is_encrypted' => true])->assertUnprocessable()->assertJsonValidationErrors('access_id');
    $this->putJson(route('accesses.update', $record), [])->assertNotFound();
    $this->deleteJson(route('accesses.destroy', $record), [])->assertNotFound();
    expect(fn () => $this->service->decryptData($ciphertext, $context, $record, $record->version()))->toThrow(ModelNotFoundException::class);
    expect($record->fresh()->content)->toBe($ciphertext);
});

test('tampered or unauthenticated ciphertext is rejected before any insert or update', function (string $corruption): void {
    $context = $this->service->context();
    $ciphertext = $this->service->encryptData('confidential content', $context);
    $record = $this->service->create(encryptedAccessForm($ciphertext));
    $raw = base64_decode($ciphertext, true);
    if (in_array($corruption, ['iv', 'tag', 'ciphertext'], true)) {
        $offset = ['iv' => 0, 'tag' => 12, 'ciphertext' => 28][$corruption];
        $raw[$offset] = chr(ord($raw[$offset]) ^ 1);
        $invalid = base64_encode($raw);
    } elseif ($corruption === 'wrong group') {
        $this->actingAs($this->outsider);
        $invalid = $this->service->encryptData('foreign secret', $this->service->context());
        $this->actingAs($this->creator);
    } else {
        $invalid = match ($corruption) {
            'not base64' => 'invalid%%payload',
            'short' => base64_encode(random_bytes(27)),
            'whitespace' => $ciphertext."\n",
            'arbitrary string' => 'client supplied string',
        };
    }
    $this->postJson(route('accesses.store'), encryptedAccessForm($invalid))->assertUnprocessable()->assertJsonValidationErrors('content');
    $this->putJson(route('accesses.update', $record), encryptedAccessForm($invalid, $record))->assertUnprocessable()->assertJsonValidationErrors('content');
    $this->postJson(route('internal.accesses.transform-data'), [...accessFormState(), 'action' => 'decrypt', 'content' => $invalid, 'is_encrypted' => true])->assertUnprocessable()->assertJsonValidationErrors('content');
    expect($record->fresh()->content)->toBe($ciphertext)->and(Access::query()->count())->toBe(1)->and($record->fresh()->version())->toBe($record->version());
})->with(['not base64', 'short', 'whitespace', 'arbitrary string', 'iv', 'tag', 'ciphertext', 'wrong group']);

test('missing or corrupt wrapped group keys cannot be silently repaired or replaced', function (string $kind): void {
    $wrapped = match ($kind) {
        'missing' => '',
        'corrupt wrapper' => 'not a Laravel payload',
        'invalid base64' => Crypt::encryptString('%%%invalid'),
        '31 bytes' => Crypt::encryptString(base64_encode(random_bytes(31))),
        '33 bytes' => Crypt::encryptString(base64_encode(random_bytes(33))),
        'whitespace' => Crypt::encryptString(base64_encode(random_bytes(32))."\n"),
    };
    $this->creator->group->forceFill(['access_encryption_key' => $wrapped])->save();
    $context = $this->service->context();
    $this->postJson(route('internal.accesses.transform-data'), ['encryption_context' => $context, 'action' => 'encrypt', 'content' => 'secret', 'is_encrypted' => false])->assertUnprocessable()->assertJsonValidationErrors('content');
    $this->putJson(route('access-encryption-key.rotate'), ['encryption_context' => $context, 'current_password' => 'Password123!'])->assertUnprocessable()->assertJsonValidationErrors('content');
    expect($this->creator->group->fresh()->getRawOriginal('access_encryption_key') === $wrapped)->toBeTrue();
    $this->postJson(route('accesses.store'), ['encryption_context' => $context, 'name' => 'Plain access', 'content' => 'plain data', 'is_encrypted' => false])->assertRedirect();
    expect(Access::query()->sole()->content)->toBe('plain data');
})->with(['missing', 'corrupt wrapper', 'invalid base64', '31 bytes', '33 bytes', 'whitespace']);

test('rotation changes every group author ciphertext atomically and leaves plaintext and other groups unchanged', function (): void {
    $oldContext = $this->service->context();
    $a = $this->service->create(encryptedAccessForm($this->service->encryptData('author A', $oldContext)));
    $plain = $this->service->create([...encryptedAccessForm('plain text'), 'is_encrypted' => false]);
    $plainOriginal = $plain->fresh()->getRawOriginal();
    $this->actingAs($this->peer);
    $b = $this->service->create(encryptedAccessForm($this->service->encryptData('author B', $oldContext)));
    $this->actingAs($this->outsider);
    $foreign = $this->service->create(encryptedAccessForm($this->service->encryptData('foreign text', $this->service->context())));
    $foreignOriginal = $foreign->fresh()->getRawOriginal();
    $foreignKey = $this->outsider->group->getRawOriginal('access_encryption_key');
    $this->actingAs($this->peer);
    $this->service->rotateKey($oldContext);
    $newContext = $this->service->context();
    expect($newContext === $oldContext)->toBeFalse();
    foreach ([[$a, 'author A', $this->creator->id], [$b, 'author B', $this->peer->id]] as [$record, $text, $author]) {
        $fresh = $record->fresh();
        expect($fresh->content === $record->content)->toBeFalse()
            ->and($fresh->user_id)->toBe($author)
            ->and($this->service->decryptData($fresh->content, $newContext, $fresh, $fresh->version()))->toBe($text);
    }
    expect($plain->fresh()->getRawOriginal())->toBe($plainOriginal)
        ->and($foreign->fresh()->getRawOriginal())->toBe($foreignOriginal)
        ->and($this->outsider->group->fresh()->getRawOriginal('access_encryption_key') === $foreignKey)->toBeTrue();
});

test('a corrupt later record rolls back earlier re-encryption and the wrapped key', function (): void {
    $context = $this->service->context();
    $valid = $this->service->create(encryptedAccessForm($this->service->encryptData('first secret', $context)));
    $original = $valid->fresh()->getRawOriginal();
    $key = $this->creator->group->getRawOriginal('access_encryption_key');
    Access::factory()->for($this->peer)->create(['content' => 'corrupt stored fixture', 'is_encrypted' => true]);
    $this->putJson(route('access-encryption-key.rotate'), ['encryption_context' => $context, 'current_password' => 'Password123!'])->assertUnprocessable()->assertJsonValidationErrors('content');
    expect($valid->fresh()->getRawOriginal())->toBe($original)
        ->and($this->creator->group->fresh()->getRawOriginal('access_encryption_key') === $key)->toBeTrue()
        ->and($this->service->decryptData($valid->content, $context, $valid, $valid->version()))->toBe('first secret');
});

test('failure wrapping a replacement key rolls back all ciphertext without logging secrets', function (): void {
    $context = $this->service->context();
    $record = $this->service->create(encryptedAccessForm($this->service->encryptData('rollback secret', $context)));
    $before = $record->fresh()->getRawOriginal();
    $key = $this->creator->group->getRawOriginal('access_encryption_key');
    $encrypter = app('encrypter');
    Crypt::swap(Mockery::mock($encrypter)->makePartial());
    Crypt::shouldReceive('encryptString')->once()->andThrow(new RuntimeException('INTERNAL-SECRET-CANARY'));
    Log::spy();
    try {
        $this->service->rotateKey($context);
        $this->fail('Rotation must fail.');
    } catch (ValidationException $exception) {
        expect(str_contains(json_encode($exception->errors()), 'INTERNAL-SECRET-CANARY'))->toBeFalse();
    }
    expect($record->fresh()->getRawOriginal())->toBe($before)
        ->and($this->creator->group->fresh()->getRawOriginal('access_encryption_key') === $key)->toBeTrue();
    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('warning');
});

test('a database failure saving a group key rolls back and does not expose wrapped-key SQL bindings', function (): void {
    $context = $this->service->context();
    $record = $this->service->create(encryptedAccessForm($this->service->encryptData('rollback secret', $context)));
    $before = $record->fresh()->getRawOriginal();
    $key = $this->creator->group->getRawOriginal('access_encryption_key');
    Group::updating(function (Group $group): void {
        throw new QueryException('mysql', 'update groups set access_encryption_key = ?', [$group->access_encryption_key], new PDOException('database failure', 23000));
    });
    Log::spy();
    try {
        $response = $this->putJson(route('access-encryption-key.rotate'), ['encryption_context' => $context, 'current_password' => 'Password123!'])->assertUnprocessable()->assertJsonValidationErrors('content');
        expect(str_contains($response->getContent(), $key))->toBeFalse();
        expect($record->fresh()->getRawOriginal())->toBe($before)
            ->and($this->creator->group->fresh()->getRawOriginal('access_encryption_key') === $key)->toBeTrue();
        Log::shouldNotHaveReceived('error');
    } finally {
        Group::flushEventListeners();
        Group::clearBootedModels();
    }
});

test('stale encrypted and plaintext forms fail after rotation even if they forge a fresh context', function (): void {
    $oldContext = $this->service->context();
    $ciphertext = $this->service->encryptData('old ciphertext', $oldContext);
    $record = $this->service->create(encryptedAccessForm($ciphertext));
    $oldVersion = $record->version();
    $this->service->rotateKey($oldContext);
    $context = $this->service->context();
    $fresh = $record->fresh();
    foreach ([true, false] as $encrypted) {
        $this->postJson(route('accesses.store'), ['encryption_context' => $oldContext, 'name' => 'Stale create', 'content' => $ciphertext, 'is_encrypted' => $encrypted])->assertJsonValidationErrors('encryption_context');
        $this->putJson(route('accesses.update', $record), ['encryption_context' => $oldContext, 'version' => $oldVersion, 'name' => 'Stale edit', 'content' => $ciphertext, 'is_encrypted' => $encrypted, 'remove_encryption' => ! $encrypted])->assertJsonValidationErrors('encryption_context');
    }
    $this->putJson(route('accesses.update', $record), [...encryptedAccessForm('plain overwrite', $fresh), 'version' => $oldVersion, 'is_encrypted' => false, 'remove_encryption' => true])->assertJsonValidationErrors('version');
    $this->putJson(route('accesses.update', $record), encryptedAccessForm($ciphertext, $fresh))->assertJsonValidationErrors('content');
    $this->postJson(route('accesses.store'), [...encryptedAccessForm($ciphertext), 'encryption_context' => $context])->assertJsonValidationErrors('content');
    $this->deleteJson(route('accesses.destroy', $record), ['encryption_context' => $oldContext, 'version' => $oldVersion])->assertJsonValidationErrors('encryption_context');
    expect(Access::query()->count())->toBe(1)->and($record->fresh()->content)->toBe($fresh->content);
});

test('two rotation submissions with the same old context cannot both commit', function (): void {
    $context = $this->service->context();
    $payload = ['encryption_context' => $context, 'current_password' => 'Password123!'];
    $this->put(route('access-encryption-key.rotate'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $afterFirst = $this->service->context();
    $this->putJson(route('access-encryption-key.rotate'), $payload)->assertUnprocessable()->assertJsonValidationErrors('encryption_context');
    expect($this->service->context())->toBe($afterFirst);
});

test('concurrent edits under one key epoch reject the older record version', function (): void {
    $record = $this->service->create([...encryptedAccessForm('plain text'), 'is_encrypted' => false]);
    $oldForm = [...encryptedAccessForm('second editor', $record), 'is_encrypted' => false];
    $this->service->update($record, [...$oldForm, 'content' => 'first editor']);
    $this->actingAs($this->peer)->putJson(route('accesses.update', $record), $oldForm)->assertUnprocessable()->assertJsonValidationErrors('version');
    $this->deleteJson(route('accesses.destroy', $record), $oldForm)->assertJsonValidationErrors('version');
    expect($record->fresh()->content)->toBe('first editor')->and($record->fresh()->user_id)->toBe($this->creator->id);
});

test('writes started before rotation fail while refreshed writes authenticate under the new key', function (): void {
    $context = $this->service->context();
    $old = encryptedAccessForm($this->service->encryptData('before rotation', $context));
    $this->service->rotateKey($context);
    $this->postJson(route('accesses.store'), $old)->assertJsonValidationErrors('encryption_context');
    $newCiphertext = $this->service->encryptData('after rotation', $this->service->context());
    $this->post(route('accesses.store'), encryptedAccessForm($newCiphertext))->assertSessionHasNoErrors()->assertRedirect();
    $record = Access::query()->sole();
    expect($this->service->decryptData($record->content, $this->service->context(), $record, $record->version()))->toBe('after rotation');
});

test('membership is reloaded inside the service and stale authenticated models cannot use the old group key', function (): void {
    $context = $this->service->context();
    $record = $this->service->create(encryptedAccessForm($this->service->encryptData('old-group secret', $context)));
    $staleAuthenticated = $this->creator->fresh();
    $this->actingAs($staleAuthenticated);
    $this->creator->group()->associate($this->outsider->group);
    $this->creator->save();
    expect(fn () => $this->service->decryptData($record->content, $context, $record, $record->version()))->toThrow(HttpException::class);
    $this->actingAs($this->creator->fresh())->putJson(route('accesses.update', $record), [])->assertNotFound();
    $this->postJson(route('accesses.store'), ['encryption_context' => $context, 'name' => 'Old form', 'content' => 'plain', 'is_encrypted' => false])->assertJsonValidationErrors('encryption_context');
    $this->actingAs($this->peer);
    expect($this->service->decryptData($record->content, $context, $record, $record->version()))->toBe('old-group secret');
});

test('unauthenticated inactive missing-group and privileged users cannot use service encryption or rotation', function (string $state): void {
    $context = $this->service->context();
    if ($state === 'guest') {
        Auth::forgetGuards();
    } elseif ($state === 'inactive') {
        $this->creator->update(['status' => 0]);
    } elseif ($state === 'missing group') {
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->actingAs($user);
    } else {
        $user = User::factory()->create(['group_id' => $this->creator->group_id]);
        if ($state !== 'no role') {
            $user->assignRole(Role::findOrCreate($state, 'web'));
        }
        $this->actingAs($user);
    }
    try {
        $this->service->encryptData('secret', $context);
        $this->fail('Encryption must be denied.');
    } catch (AuthorizationException|HttpException $exception) {
        expect(true)->toBeTrue();
    }
    try {
        $this->service->rotateKey($context);
        $this->fail('Rotation must be denied.');
    } catch (AuthorizationException|HttpException $exception) {
        expect(true)->toBeTrue();
    }
})->with(['guest', 'inactive', 'missing group', 'no role', 'admin', 'profiler']);

test('operation-specific policy denials also apply inside service calls', function (): void {
    $context = $this->service->context();
    $record = $this->service->create(encryptedAccessForm($this->service->encryptData('secret', $context)));
    $this->partialMock(AccessPolicy::class)->shouldReceive('update')->andReturnFalse();
    expect(fn () => $this->service->encryptData('replacement', $context, $record, $record->version()))->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->update($record, encryptedAccessForm('replacement', $record)))->toThrow(AuthorizationException::class);
});

test('rotation requires a password and cannot accept client-supplied ownership or replacement keys', function (): void {
    $context = $this->service->context();
    $wrapped = $this->creator->group->getRawOriginal('access_encryption_key');
    $this->putJson(route('access-encryption-key.rotate'), ['encryption_context' => $context, 'current_password' => 'wrong'])->assertJsonValidationErrors('current_password');
    foreach (['encryption_key', 'encryption_key_confirmation', 'access_encryption_key', 'group_id', 'user_id'] as $field) {
        $this->putJson(route('access-encryption-key.rotate'), ['encryption_context' => $context, 'current_password' => 'Password123!', $field => 'client-supplied'])->assertJsonValidationErrors($field);
    }
    expect($this->creator->group->fresh()->getRawOriginal('access_encryption_key') === $wrapped)->toBeTrue();
    $this->postJson('/settings/access-encryption-key', ['encryption_key' => 'ForbiddenKey123!'])->assertNotFound();
});

test('keys and plaintext are absent from props serialization validation errors flash and logs', function (): void {
    $group = $this->creator->group;
    $wrapped = $group->getRawOriginal('access_encryption_key');
    $encoded = Crypt::decryptString($wrapped);
    $raw = base64_decode($encoded, true);
    Log::spy();
    $response = $this->get(route('accesses.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('encryptionContext')->missing('auth.user.has_access_encryption_key')->missing('auth.user.access_encryption_key'));
    $security = $this->withSession(['auth.password_confirmed_at' => time()])->get(route('security.edit'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('canManageAccessEncryptionKey', true)->has('accessEncryptionContext')->missing('hasAccessEncryptionKey'));
    foreach ([$wrapped, $encoded, $raw] as $secret) {
        expect(str_contains($response->getContent(), $secret))->toBeFalse()
            ->and(str_contains($security->getContent(), $secret))->toBeFalse()
            ->and(str_contains($group->toJson(), $secret))->toBeFalse();
    }
    $this->from(route('accesses.index'))->post(route('accesses.store'), ['encryption_context' => $this->service->context(), 'name' => 'Invalid', 'content' => 'PLAINTEXT-CANARY', 'is_encrypted' => true, 'access_encryption_key' => $wrapped])->assertSessionHasErrors('content')->assertSessionMissing('_old_input.content')->assertSessionMissing('_old_input.access_encryption_key');
    $this->putJson(route('access-encryption-key.rotate'), ['encryption_context' => $this->service->context(), 'current_password' => 'Password123!'])->assertRedirect();
    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('warning');
    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('debug');
});

test('cancelled model writes also roll rotation back instead of committing mixed keys', function (string $cancel): void {
    $context = $this->service->context();
    $first = $this->service->create(encryptedAccessForm($this->service->encryptData('first secret', $context)));
    $second = $this->service->create(encryptedAccessForm($this->service->encryptData('second secret', $context)));
    $beforeFirst = $first->fresh()->getRawOriginal();
    $beforeSecond = $second->fresh()->getRawOriginal();
    if ($cancel === 'access') {
        Access::updating(fn (Access $access): bool => $access->id !== $second->id);
    } else {
        Group::updating(fn (): bool => false);
    }
    try {
        $this->putJson(route('access-encryption-key.rotate'), ['encryption_context' => $context, 'current_password' => 'Password123!'])->assertUnprocessable()->assertJsonValidationErrors('content');
        expect($this->service->context())->toBe($context)
            ->and($first->fresh()->getRawOriginal())->toBe($beforeFirst)
            ->and($second->fresh()->getRawOriginal())->toBe($beforeSecond);
    } finally {
        Access::flushEventListeners();
        Group::flushEventListeners();
        Group::clearBootedModels();
    }
})->with(['access', 'group']);
