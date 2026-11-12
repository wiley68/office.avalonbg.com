<?php

namespace App\Http\Requests;

use App\Models\Access;
use App\Support\Translations;
use Illuminate\Foundation\Http\FormRequest;

class DestroyAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $access = $this->route('access');

        return $access instanceof Access && ($this->user()?->can('delete', $access) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'encryption_context' => ['required', 'string', 'size:64'],
            'version' => ['required', 'string', 'size:64'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'encryption_context.*' => Translations::get('accesses.errors.stale_form'),
            'version.*' => Translations::get('accesses.errors.stale_form'),
        ];
    }
}
