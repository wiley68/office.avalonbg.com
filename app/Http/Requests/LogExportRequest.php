<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesExportPassword;
use Illuminate\Foundation\Http\FormRequest;

class LogExportRequest extends FormRequest
{
    use ValidatesExportPassword;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return array_merge([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ], $this->exportPasswordRules());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'date_from.required' => 'Началната дата е задължителна.',
            'date_to.required' => 'Крайната дата е задължителна.',
            'date_to.after_or_equal' => 'Крайната дата трябва да е след или равна на началната.',
        ], $this->exportPasswordMessages());
    }
}
