<?php

namespace App\Http\Requests\Concerns;

trait ValidatesExportPassword
{
    /**
     * @return array<string, list<string>>
     */
    protected function exportPasswordRules(): array
    {
        $minLength = max(1, (int) config('exports.password_min_length', 8));

        return [
            'password' => ['required', 'string', 'min:'.$minLength, 'max:128'],
            'password_confirmation' => ['required', 'same:password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function exportPasswordMessages(): array
    {
        $minLength = max(1, (int) config('exports.password_min_length', 8));

        return [
            'password.required' => 'Паролата за архива е задължителна.',
            'password.min' => 'Паролата за архива трябва да е поне '.$minLength.' символа.',
            'password.max' => 'Паролата за архива не може да е повече от 128 символа.',
            'password_confirmation.required' => 'Потвърждението на паролата е задължително.',
            'password_confirmation.same' => 'Потвърждението на паролата не съвпада.',
        ];
    }
}
