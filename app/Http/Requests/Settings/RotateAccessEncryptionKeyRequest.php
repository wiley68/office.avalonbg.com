<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Models\Access;
use App\Support\Translations;
use Illuminate\Foundation\Http\FormRequest;

class RotateAccessEncryptionKeyRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('manageEncryptionKey', Access::class) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'encryption_context' => ['required', 'string', 'size:64'],
            'encryption_key' => ['prohibited'],
            'encryption_key_confirmation' => ['prohibited'],
            'access_encryption_key' => ['prohibited'],
            'group_id' => ['prohibited'],
            'user_id' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.required' => Translations::get('accesses.encryption_key.password_required'),
            'current_password.current_password' => Translations::get('accesses.encryption_key.password_invalid'),
            '*.prohibited' => Translations::get('accesses.errors.key_override_prohibited'),
        ];
    }
}
