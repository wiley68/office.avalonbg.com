<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Normalizer;
use RuntimeException;

class TextCryptoService
{
    private Encrypter $encrypter;

    public function __construct()
    {
        $secret = config('app.secret_key');

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('APP_SECRET_KEY is not configured.');
        }

        $key = hash('sha256', $secret, true);
        $this->encrypter = new Encrypter($key, config('app.cipher'));
    }

    public function encryptPlainText(string $plain): string
    {
        return $this->encrypter->encryptString($plain);
    }

    /**
     * @throws DecryptException
     */
    public function decryptToPlainText(string $payload): string
    {
        $payload = self::normalizePayloadForDecrypt($payload);

        return $this->encrypter->decryptString($payload);
    }

    /**
     * Подготовка за декриптиране: премахва невидими символи, whitespace (често при копиране от чат),
     * Unicode NFKC. Не поправя заменени букви (напр. арабски Unicode вместо латиница в Base64).
     */
    public static function normalizePayloadForDecrypt(string $payload): string
    {
        $payload = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $payload) ?? $payload;

        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($payload, Normalizer::FORM_KC);
            if ($normalized !== false) {
                $payload = $normalized;
            }
        }

        return preg_replace('/\s+/u', '', $payload) ?? $payload;
    }
}
