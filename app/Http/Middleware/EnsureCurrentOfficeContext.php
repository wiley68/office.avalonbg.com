<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentOfficeContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = $request->header('X-Office-Context');

        if ($context !== null && ! hash_equals($request->user()?->authorizationContext() ?? '', $context)) {
            return response()->json(['message' => 'Session context changed. Reload the page.'], 419);
        }

        return $next($request);
    }
}
