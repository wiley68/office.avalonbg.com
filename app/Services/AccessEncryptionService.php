<?php

namespace App\Services;

use App\Models\Access;
use App\Models\Group;
use App\Models\User;
use App\Support\Translations;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use SensitiveParameter;

class AccessEncryptionService
{
    public function context(): string
    {
        return $this->withinGroup('viewAny', null, fn (#[SensitiveParameter] Group $group): string => $this->contextFor($group));
    }

    public function encryptData(#[SensitiveParameter] string $plaintext, string $context, ?Access $access = null, ?string $version = null): string
    {
        return $this->withinGroup($access === null ? 'create' : 'update', $access, function (#[SensitiveParameter] Group $group, User $user, ?Access $record) use ($plaintext, $context, $version): string {
            $this->assertContext($group, $context);
            $this->assertVersion($record, $version);

            return $this->encrypt($plaintext, $this->keyFor($group));
        });
    }

    public function decryptData(#[SensitiveParameter] string $payload, string $context, ?Access $access = null, ?string $version = null): string
    {
        return $this->withinGroup($access === null ? 'transformData' : 'view', $access, function (#[SensitiveParameter] Group $group, User $user, ?Access $record) use ($payload, $context, $version): string {
            $this->assertContext($group, $context);
            $this->assertVersion($record, $version);

            return $this->decrypt($payload, $this->keyFor($group));
        });
    }

    /** @param array{name: string, category?: ?string, content: string, is_encrypted: bool|int|string, encryption_context: string} $attributes */
    public function create(#[SensitiveParameter] array $attributes): Access
    {
        return $this->withinGroup('create', null, function (#[SensitiveParameter] Group $group, User $user) use ($attributes): Access {
            $this->assertContext($group, $attributes['encryption_context']);
            $this->assertCiphertext($group, $attributes);

            return $group->accesses()->create([
                ...Arr::only($attributes, ['name', 'category', 'content', 'is_encrypted']),
                'user_id' => $user->id,
            ]);
        });
    }

    /** @param array{name: string, category?: ?string, content: string, is_encrypted: bool|int|string, encryption_context: string, version: string} $attributes */
    public function update(Access $access, #[SensitiveParameter] array $attributes): Access
    {
        return $this->withinGroup('update', $access, function (#[SensitiveParameter] Group $group, User $user, Access $record) use ($attributes): Access {
            $this->assertContext($group, $attributes['encryption_context']);
            $this->assertVersion($record, $attributes['version']);
            $this->assertCiphertext($group, $attributes);
            $record->update(Arr::only($attributes, ['name', 'category', 'content', 'is_encrypted']));

            return $record;
        });
    }

    public function delete(Access $access, string $context, string $version): void
    {
        $this->withinGroup('delete', $access, function (#[SensitiveParameter] Group $group, User $user, Access $record) use ($context, $version): void {
            $this->assertContext($group, $context);
            $this->assertVersion($record, $version);
            $record->delete();
        });
    }

    public function rotateKey(string $context): void
    {
        $this->withinGroup('manageEncryptionKey', null, function (#[SensitiveParameter] Group $group) use ($context): void {
            $this->assertContext($group, $context);
            $oldKey = $this->keyFor($group);
            $newKey = random_bytes(32);

            $records = $group->accesses()->where('is_encrypted', true)->orderBy('id')->lockForUpdate()->get();

            foreach ($records as $record) {
                if (! $record->update(['content' => $this->encrypt($this->decrypt($record->content, $oldKey), $newKey)])) {
                    throw ValidationException::withMessages(['content' => Translations::get('accesses.errors.operation_failed')]);
                }
            }

            try {
                $wrappedKey = Crypt::encryptString(base64_encode($newKey));
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages(['content' => Translations::get('accesses.errors.key_unavailable')]);
            }

            if (! $group->forceFill(['access_encryption_key' => $wrappedKey])->save()) {
                throw ValidationException::withMessages(['content' => Translations::get('accesses.errors.operation_failed')]);
            }
        });
    }

    /** @param Closure(Group, User, ?Access): mixed $operation */
    private function withinGroup(string $ability, ?Access $access, Closure $operation): mixed
    {
        $authenticated = Auth::user();
        abort_unless($authenticated instanceof User && $authenticated->group_id !== null, 403);

        try {
            return (new Group)->getConnection()->transaction(function () use ($authenticated, $ability, $access, $operation): mixed {
                $group = Group::query()->select('id')->whereKey($authenticated->group_id)->lockForUpdate()->first();
                abort_if($group === null, 403);

                $user = User::query()->whereKey($authenticated->id)->lockForUpdate()->first();
                abort_unless($user !== null && $user->isActive() && $user->group_id === $group->id, 403);

                Gate::forUser($user)->authorize('viewAny', Access::class);

                $record = $access === null ? null : Access::query()
                    ->where('group_id', $group->id)->whereKey($access->id)->lockForUpdate()->firstOrFail();

                Gate::forUser($user)->authorize($ability, $record ?? Access::class);

                $group = Group::query()->select(['id', 'access_encryption_key'])->whereKey($group->id)->lockForUpdate()->firstOrFail();

                return $operation($group, $user, $record);
            }, 3);
        } catch (QueryException $exception) {
            throw ValidationException::withMessages(['content' => Translations::get('accesses.errors.operation_failed')]);
        }
    }

    private function contextFor(#[SensitiveParameter] Group $group): string
    {
        return hash('sha256', $group->id.'|'.$group->getRawOriginal('access_encryption_key'));
    }

    private function assertContext(#[SensitiveParameter] Group $group, string $context): void
    {
        if (! hash_equals($this->contextFor($group), $context)) {
            throw ValidationException::withMessages(['encryption_context' => Translations::get('accesses.errors.stale_form')]);
        }
    }

    private function assertVersion(?Access $record, ?string $version): void
    {
        if ($record !== null && ($version === null || ! hash_equals($record->version(), $version))) {
            throw ValidationException::withMessages(['version' => Translations::get('accesses.errors.stale_form')]);
        }
    }

    /** @param array{content: string, is_encrypted: bool|int|string} $attributes */
    private function assertCiphertext(#[SensitiveParameter] Group $group, #[SensitiveParameter] array $attributes): void
    {
        if ((bool) $attributes['is_encrypted']) {
            $this->decrypt($attributes['content'], $this->keyFor($group));
        }
    }

    private function keyFor(#[SensitiveParameter] Group $group): string
    {
        try {
            $encoded = Crypt::decryptString((string) $group->getRawOriginal('access_encryption_key'));
        } catch (DecryptException|RuntimeException $exception) {
            throw ValidationException::withMessages(['content' => Translations::get('accesses.errors.key_unavailable')]);
        }

        $key = base64_decode($encoded, true);

        if ($key === false || strlen($key) !== 32 || base64_encode($key) !== $encoded) {
            throw ValidationException::withMessages(['content' => Translations::get('accesses.errors.key_unavailable')]);
        }

        return $key;
    }

    private function encrypt(#[SensitiveParameter] string $plaintext, #[SensitiveParameter] string $key): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);

        if ($ciphertext === false) {
            throw ValidationException::withMessages(['content' => Translations::get('accesses.errors.operation_failed')]);
        }

        return base64_encode($iv.$tag.$ciphertext);
    }

    private function decrypt(#[SensitiveParameter] string $payload, #[SensitiveParameter] string $key): string
    {
        $raw = base64_decode($payload, true);

        if ($raw === false || strlen($raw) < 28 || base64_encode($raw) !== $payload) {
            throw ValidationException::withMessages(['content' => Translations::get('accesses.errors.invalid_ciphertext')]);
        }

        $plaintext = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));

        if ($plaintext === false) {
            throw ValidationException::withMessages(['content' => Translations::get('accesses.errors.invalid_ciphertext')]);
        }

        return $plaintext;
    }
}
