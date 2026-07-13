<?php

namespace App\Http\Requests;

use App\Models\Document;
use App\Rules\AllowedDocumentFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Document::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480', new AllowedDocumentFile],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
