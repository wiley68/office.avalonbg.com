<?php

namespace App\Http\Requests;

use App\Models\Access;
use App\Support\Translations;
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
            'access_id' => ['nullable', 'integer', Rule::exists('accesses', 'id')->where('group_id', $this->user()->group_id)],
            'version' => ['nullable', 'required_with:access_id', 'string', 'size:64'],
            'encryption_context' => ['required', 'string', 'size:64'],
            'action' => ['required', 'string', Rule::in(['encrypt', 'decrypt'])],
            'content' => ['required', 'string'],
            'is_encrypted' => ['required', 'boolean'],
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
