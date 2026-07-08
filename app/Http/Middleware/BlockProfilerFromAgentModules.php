<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Профайлърите нямат достъп до оркестратор/бележки агенти и свързаните API.
 */
class BlockProfilerFromAgentModules
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRole(UserRole::Profiler->value)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => 'Профайлърите нямат достъп до агентските модули.',
            ], 403);
        }

        return redirect()->route('dashboard');
    }
}
