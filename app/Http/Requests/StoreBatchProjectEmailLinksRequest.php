<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBatchProjectEmailLinksRequest extends FormRequest
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
            'messages' => ['required', 'array', 'min:1', 'max:50'],
            'messages.*.folder' => ['required', 'string', 'max:255'],
            'messages.*.imap_uid' => ['required', 'integer', 'min:1'],
            'messages.*.uidvalidity' => ['required', 'integer', 'min:1'],
            'messages.*.subject' => ['nullable', 'string', 'max:500'],
            'messages.*.from_name' => ['nullable', 'string', 'max:255'],
            'messages.*.from_address' => ['nullable', 'string', 'max:255'],
            'messages.*.sent_at' => ['nullable', 'date'],
        ];
    }
}
