<?php

namespace App\Services;

use App\Models\Access;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use RuntimeException;

class AccessEncryptionService
{
    public function hasStoredKey(User $user): bool
    {
        return filled($user->access_encryption_key);
    }

    public function storeKey(User $user, string $plainKey): void
    {
        $user->update([
            'access_encryption_key' => Crypt::encryptString($plainKey),
        ]);
    }

    public function resolveKey(User $user): ?string
    {
        if (! $this->hasStoredKey($user)) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $user->access_encryption_key);
        } catch (DecryptException) {
            return null;
        }
    }

    public function requireKey(User $user): string
    {
        $key = $this->resolveKey($user);

        if ($key === null || $key === '') {
            throw new RuntimeException('Access encryption key is not configured.');
        }

        return $key;
    }

    public function encryptData(string $plaintext, string $userKey): string
    {
        $derivedKey = hash('sha256', $userKey, true);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $derivedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decryptData(string $payload, string $userKey): string
    {
        $raw = base64_decode($payload, true);

        if ($raw === false || strlen($raw) < 28) {
            throw new InvalidArgumentException('Invalid encrypted payload.');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $derivedKey = hash('sha256', $userKey, true);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $derivedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plaintext === false) {
            throw new InvalidArgumentException('Decryption failed. Check your encryption key.');
        }

        return $plaintext;
    }

    public function rotateKey(User $user, string $newKey): void
    {
        $oldKey = $this->resolveKey($user);

        if ($oldKey === null || $oldKey === '') {
            $this->storeKey($user, $newKey);

            return;
        }

        Access::query()
            ->where('user_id', $user->id)
            ->where('is_encrypted', true)
            ->each(function (Access $access) use ($oldKey, $newKey): void {
                $plaintext = $this->decryptData($access->content, $oldKey);
                $access->update([
                    'content' => $this->encryptData($plaintext, $newKey),
                ]);
            });

        $this->storeKey($user, $newKey);
    }
}
