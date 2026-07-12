<?php

namespace App\Http\Requests;

use App\Models\Document;
use App\Services\DocumentStorageService;
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
        $allowedMimes = app(DocumentStorageService::class)->allowedMimeTypes();

        return [
            'file' => ['required', 'file', 'max:20480', 'mimetypes:'.implode(',', $allowedMimes)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
