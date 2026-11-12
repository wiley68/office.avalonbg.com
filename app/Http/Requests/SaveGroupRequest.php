<?php

namespace App\Http\Requests;

use App\Models\Group;
use App\Support\Translations;
use Illuminate\Foundation\Http\FormRequest;

class SaveGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        return $group instanceof Group
            ? ($this->user()?->can('update', $group) ?? false)
            : ($this->user()?->can('create', Group::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->replace($this->only('name'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => Translations::get('groups.name_required'),
            'name.string' => Translations::get('groups.name_invalid'),
            'name.max' => Translations::get('groups.name_invalid'),
        ];
    }
}
