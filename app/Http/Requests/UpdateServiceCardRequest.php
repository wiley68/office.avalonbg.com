<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceCardRequest extends FormRequest
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
            'rakovoditel_id' => ['sometimes', 'nullable', 'integer', Rule::exists('service.members', 'id')],
            'datecard' => ['sometimes', 'required', 'date'],
            'name' => ['sometimes', 'required', 'integer', Rule::exists('service.contacts', 'id')],
            'special' => ['sometimes', 'required', 'string', Rule::in(['Спешна поръчка', 'Нормална поръчка'])],
            'product' => ['sometimes', 'required', 'string', 'max:128'],
            'varanty' => ['sometimes', 'required', 'string', Rule::in(['Гаранционен', 'Извън гаранционен'])],
            'problem' => ['sometimes', 'nullable', 'string'],
            'serviseproblem' => ['sometimes', 'nullable', 'string'],
            'serviseproblemtechnik_id' => ['sometimes', 'required', 'integer', Rule::exists('service.members', 'id')],
            'dopclient' => ['sometimes', 'nullable', 'string'],
            'datepredavane' => ['sometimes', 'required', 'date'],
            'saobshtilclient_id' => ['sometimes', 'required', 'integer', Rule::exists('service.members', 'id')],
            'clientopisanie' => ['sometimes', 'nullable', 'string', 'max:512'],
            'etap' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'Приета за сервиз',
                    'Диагностика',
                    'Извършва се ремонта',
                    'Изпратен за гаранционен ремонт',
                    'Приключен ремонт',
                ]),
            ],
        ];
    }
}
