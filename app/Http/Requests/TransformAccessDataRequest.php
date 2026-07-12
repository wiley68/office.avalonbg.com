<?php

namespace App\Http\Requests;

use App\Models\Access;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransformAccessDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transformData', Access::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['encrypt', 'decrypt'])],
            'content' => ['required', 'string'],
            'is_encrypted' => ['required', 'boolean'],
        ];
    }
}
