<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectEmailLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project !== null && $this->user()?->can('update', $project);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'folder' => ['required', 'string', 'max:255'],
            'imap_uid' => ['required', 'integer', 'min:1'],
            'uidvalidity' => ['required', 'integer', 'min:1'],
            'subject' => ['nullable', 'string', 'max:500'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'string', 'max:255'],
            'sent_at' => ['nullable', 'date'],
        ];
    }
}
