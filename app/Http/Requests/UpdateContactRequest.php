<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
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
            'citi_id' => ['sometimes', 'required', 'integer'],
            'last_name' => ['sometimes', 'required', 'string', 'max:24'],
            'eik' => ['sometimes', 'nullable', 'string', 'max:9'],
            'info' => ['sometimes', 'nullable', 'string', 'max:128'],
            'name' => ['sometimes', 'nullable', 'string', 'max:24'],
            'second_name' => ['sometimes', 'nullable', 'string', 'max:24'],
            'dlaznosti_id' => ['sometimes', 'nullable', 'integer'],
            'gsm_1_m' => ['sometimes', 'nullable', 'string', 'max:128'],
            'gsm_2_g' => ['sometimes', 'nullable', 'string', 'max:128'],
            'gsm_3_v' => ['sometimes', 'nullable', 'string', 'max:128'],
            'tel1' => ['sometimes', 'nullable', 'string', 'max:128'],
            'tel2' => ['sometimes', 'nullable', 'string', 'max:128'],
            'fax' => ['sometimes', 'nullable', 'string', 'max:128'],
            'email' => ['sometimes', 'nullable', 'string', 'max:45'],
            'web' => ['sometimes', 'nullable', 'string', 'max:45'],
            'address' => ['sometimes', 'nullable', 'string', 'max:256'],
            'b_phone' => ['sometimes', 'nullable', 'string', 'max:128'],
            'b_email' => ['sometimes', 'nullable', 'string', 'max:45'],
            'b_im' => ['sometimes', 'nullable', 'string', 'max:45'],
            'im' => ['sometimes', 'nullable', 'string', 'max:45'],
            'note' => ['sometimes', 'nullable', 'string'],
            'firm' => ['sometimes', 'nullable', 'string', 'max:256'],
        ];
    }
}
