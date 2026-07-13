<?php

namespace App\Http\Requests;

use App\Models\Document;
use App\Services\DocumentStorageService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceDocumentFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document
            && ($this->user()?->can('update', $document) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedMimes = app(DocumentStorageService::class)->allowedMimeTypes();

        return [
            'file' => ['required', 'file', 'max:20480', 'mimetypes:'.implode(',', $allowedMimes)],
        ];
    }
}
