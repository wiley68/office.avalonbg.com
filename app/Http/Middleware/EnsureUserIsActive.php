<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Translations;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * @var list<string>
     */
    private const ALLOWED_ROUTE_NAMES = [
        'logout',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isActive()) {
            return $next($request);
        }

        if ($request->routeIs(self::ALLOWED_ROUTE_NAMES)) {
            return $next($request);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            abort(403, Translations::get('auth.inactive'));
        }

        return redirect()
            ->route('login')
            ->withErrors(['email' => Translations::get('auth.inactive')]);
    }
}
