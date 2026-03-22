<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Администраторите нямат достъп до оркестратор/бележки агенти и свързаните API.
 */
class RedirectAdminFromAgentModules
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRole('admin')) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => 'Администраторите нямат достъп до агентските модули.',
            ], 403);
        }

        return redirect()->route('dashboard');
    }
}
