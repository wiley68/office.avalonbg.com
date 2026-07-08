<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->hasEnabledTwoFactorAuthentication()) {
            return $next($request);
        }

        if ($request->routeIs(
            'security.edit',
            'user-password.update',
            'two-factor.*',
            'logout',
            'verification.*',
            'profile.*',
            'appearance.*',
        )) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => 'За достъп е необходимо да активирате двуфакторна автентикация.',
            ], 403);
        }

        return redirect()->route('security.edit');
    }
}
