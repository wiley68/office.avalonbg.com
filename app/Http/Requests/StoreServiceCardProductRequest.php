<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceCardProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:256'],
            'price' => ['required', 'numeric', 'min:0'],
            'vat' => ['required', 'string', Rule::in(['Yes', 'No'])],
            'broi' => ['required', 'integer', 'min:1'],
            'ed_cena' => ['required', 'numeric', 'min:0'],
        ];
    }
}
