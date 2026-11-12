<?php

namespace App\Http\Requests;

use App\Support\Translations;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $access = $this->route('access');

        return $access !== null && ($this->user()?->can('update', $access) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'max:40'],
            'content' => ['required', 'string'],
            'is_encrypted' => ['required', 'boolean'],
            'remove_encryption' => ['sometimes', 'boolean'],
            'encryption_context' => ['required', 'string', 'size:64'],
            'version' => ['required', 'string', 'size:64'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->route('access')?->is_encrypted
                && ! $this->boolean('is_encrypted')
                && ! $this->boolean('remove_encryption')) {
                $validator->errors()->add('remove_encryption', Translations::get('accesses.errors.confirm_remove_encryption'));
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'encryption_context.*' => Translations::get('accesses.errors.stale_form'),
            'version.*' => Translations::get('accesses.errors.stale_form'),
        ];
    }
}
