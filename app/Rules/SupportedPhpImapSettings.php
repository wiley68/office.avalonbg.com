<?php

namespace App\Rules;

use App\Enums\ImapEncryption;
use App\Support\Translations;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;

class SupportedPhpImapSettings implements ValidationRule, ValidatorAwareRule
{
    private Validator $validator;

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $encryption = ImapEncryption::tryFrom((string) $value);

        if ($encryption === null) {
            return;
        }

        $port = (int) $this->validator->getData()['port'];

        if ($encryption === ImapEncryption::Tls && $port === 143) {
            $fail(Translations::get('settings.email.errors.starttls_unsupported'));
        }
    }
}
