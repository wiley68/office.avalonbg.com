<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\Translations;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->route('user');

        return $this->user()?->can('update', $user) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => $this->optionalPasswordRules(),
            'group_id' => app(UserPolicy::class)->manageableRole($this->user()) === UserRole::User
                ? ['required', 'integer', Rule::exists('groups', 'id')]
                : ['prohibited'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'group_id.required' => Translations::get('groups.assignment_required'),
            'group_id.integer' => Translations::get('groups.assignment_invalid'),
            'group_id.exists' => Translations::get('groups.assignment_invalid'),
            'group_id.prohibited' => Translations::get('groups.assignment_prohibited'),
        ];
    }
}
