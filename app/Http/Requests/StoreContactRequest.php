<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
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
            'citi_id' => ['required', 'integer'],
            'last_name' => ['required', 'string', 'max:24'],
            'eik' => ['nullable', 'string', 'max:9'],
            'info' => ['nullable', 'string', 'max:128'],
            'name' => ['nullable', 'string', 'max:24'],
            'second_name' => ['nullable', 'string', 'max:24'],
            'dlaznosti_id' => ['nullable', 'integer'],
            'gsm_1_m' => ['nullable', 'string', 'max:128'],
            'gsm_2_g' => ['nullable', 'string', 'max:128'],
            'gsm_3_v' => ['nullable', 'string', 'max:128'],
            'tel1' => ['nullable', 'string', 'max:128'],
            'tel2' => ['nullable', 'string', 'max:128'],
            'fax' => ['nullable', 'string', 'max:128'],
            'email' => ['nullable', 'string', 'max:45'],
            'web' => ['nullable', 'string', 'max:45'],
            'address' => ['nullable', 'string', 'max:256'],
            'b_phone' => ['nullable', 'string', 'max:128'],
            'b_email' => ['nullable', 'string', 'max:45'],
            'b_im' => ['nullable', 'string', 'max:45'],
            'im' => ['nullable', 'string', 'max:45'],
            'note' => ['nullable', 'string'],
            'firm' => ['nullable', 'string', 'max:256'],
        ];
    }
}
