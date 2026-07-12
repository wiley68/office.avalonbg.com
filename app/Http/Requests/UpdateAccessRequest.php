<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
