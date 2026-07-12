<?php

namespace App\Http\Requests;

use App\Models\Access;
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
        ];
    }
}
