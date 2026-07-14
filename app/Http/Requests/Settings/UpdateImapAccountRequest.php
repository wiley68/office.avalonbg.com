<?php

namespace App\Http\Requests\Settings;

use App\Enums\ImapEncryption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateImapAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOfficeUser() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', Rule::enum(ImapEncryption::class)],
            'username' => ['required', 'string', 'max:255'],
            'password' => [
                Rule::requiredIf(fn (): bool => $this->user()?->imapAccount === null),
                'nullable',
                'string',
                'max:255',
            ],
            'default_folder' => ['required', 'string', 'max:255'],
        ];
    }
}
