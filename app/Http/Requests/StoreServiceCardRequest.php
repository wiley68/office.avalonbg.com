<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceCardRequest extends FormRequest
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
            'rakovoditel_id' => ['nullable', 'integer', Rule::exists('service.members', 'id')],
            'datecard' => ['required', 'date'],
            'name' => ['required', 'integer', Rule::exists('service.contacts', 'id')],
            'special' => ['required', 'string', Rule::in(['Спешна поръчка', 'Нормална поръчка'])],
            'product' => ['required', 'string', 'max:128'],
            'varanty' => ['required', 'string', Rule::in(['Гаранционен', 'Извън гаранционен'])],
            'problem' => ['nullable', 'string'],
            'serviseproblem' => ['nullable', 'string'],
            'serviseproblemtechnik_id' => ['required', 'integer', Rule::exists('service.members', 'id')],
            'dopclient' => ['nullable', 'string'],
            'datepredavane' => ['required', 'date'],
            'saobshtilclient_id' => ['required', 'integer', Rule::exists('service.members', 'id')],
            'clientopisanie' => ['nullable', 'string', 'max:512'],
            'etap' => [
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
