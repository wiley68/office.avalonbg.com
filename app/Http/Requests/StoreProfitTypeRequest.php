<?php

namespace App\Http\Requests;

use App\Enums\ProfitTypeKind;
use App\Models\ProfitType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfitTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProfitType::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('profit_types', 'name')->where(
                    fn ($query) => $query->where('kind', $this->input('kind')),
                ),
            ],
            'kind' => ['required', Rule::enum(ProfitTypeKind::class)],
        ];
    }
}
