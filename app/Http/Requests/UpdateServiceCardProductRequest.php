<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceCardProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:256'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'vat' => ['sometimes', 'required', 'string', Rule::in(['Yes', 'No'])],
            'broi' => ['sometimes', 'required', 'integer', 'min:1'],
            'ed_cena' => ['sometimes', 'required', 'numeric', 'min:0'],
        ];
    }
}
