<?php

namespace App\Http\Requests;

use App\Models\ProfitEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfitExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ProfitEntry::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.required' => 'Началната дата е задължителна.',
            'date_to.required' => 'Крайната дата е задължителна.',
            'date_to.after_or_equal' => 'Крайната дата трябва да е след или равна на началната.',
            'format.required' => 'Форматът е задължителен.',
            'format.in' => 'Форматът трябва да е xlsx или pdf.',
        ];
    }
}
