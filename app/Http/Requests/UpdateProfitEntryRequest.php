<?php

namespace App\Http\Requests;

use App\Models\ProfitType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfitEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('profit');

        return $entry !== null && ($this->user()?->can('update', $entry) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'profit_type_id' => ['required', 'integer', Rule::exists(ProfitType::class, 'id')],
            'date' => ['required', 'date'],
            'document_number' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ];
    }
}
