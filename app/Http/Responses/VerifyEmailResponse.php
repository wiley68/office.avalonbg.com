<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->user()?->must_change_password) {
            return redirect()->route('password.change.edit');
        }

        return redirect()->intended(config('fortify.home') . '?verified=1');
    }
}
