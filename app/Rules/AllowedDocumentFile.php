<?php

namespace App\Rules;

use App\Services\DocumentStorageService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

class AllowedDocumentFile implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail(__('validation.file', ['attribute' => $attribute]));

            return;
        }

        if (! app(DocumentStorageService::class)->isAllowedUpload($value)) {
            $allowed = implode(', ', app(DocumentStorageService::class)->allowedMimeTypes());

            $fail(__('validation.mimetypes', [
                'attribute' => $attribute,
                'values' => $allowed,
            ]));
        }
    }
}
