<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Models\Access;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class RotateAccessEncryptionKeyRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('manageEncryptionKey', Access::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'encryption_key' => ['required', 'string', 'min:8', 'max:255'],
            'encryption_key_confirmation' => ['required', 'same:encryption_key'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if ($user === null || ! Hash::check((string) $this->input('current_password'), $user->password)) {
                $validator->errors()->add('current_password', 'The provided password is incorrect.');
            }
        });
    }
}
