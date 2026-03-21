<?php

namespace App\Http\Middleware;

use App\Enums\AgentContext;
use App\Support\CurrentAgentContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Задава контекста на агентския разговор за POST заявките към агентите.
 * Изчистването при SSE се случва в края на потока (AgentSseResponse); при не-stream отговори — тук.
 */
class EnsureAgentConversationContext
{
    public function handle(Request $request, Closure $next, string $context): Response
    {
        CurrentAgentContext::set(AgentContext::from($context));

        $response = $next($request);

        if (! $response instanceof StreamedResponse) {
            CurrentAgentContext::clear();
        }

        return $response;
    }
}
