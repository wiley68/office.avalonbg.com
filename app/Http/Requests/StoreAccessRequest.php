<?php

namespace App\Http\Requests;

use App\Models\Access;
use App\Support\Translations;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Access::class) ?? false;
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
            'encryption_context' => ['required', 'string', 'size:64'],
        ];
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
