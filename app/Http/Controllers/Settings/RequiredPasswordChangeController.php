<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RequiredPasswordChangeController extends Controller
{
    public function edit(): Response|RedirectResponse
    {
        $user = request()->user();

        if ($user === null || ! $user->must_change_password) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('settings/RequiredPasswordChange');
    }

    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => $request->password,
            'must_change_password' => false,
        ]);

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('security.edit');
        }

        return redirect()->route('dashboard');
    }
}
