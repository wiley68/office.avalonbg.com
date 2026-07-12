<?php

namespace App\Http\Requests\Settings;

use App\Models\Access;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccessEncryptionKeyRequest extends FormRequest
{
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
            'encryption_key' => ['required', 'string', 'min:8', 'max:255'],
            'encryption_key_confirmation' => ['required', 'same:encryption_key'],
        ];
    }
}
