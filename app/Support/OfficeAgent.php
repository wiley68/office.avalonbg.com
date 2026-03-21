<?php

namespace App\Support;

use App\Ai\Agents\ConversationalOfficeAgent;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;

/**
 * Изпълнение на офис агент с опционален разговор (запис в agent_conversations).
 */
final class OfficeAgent
{
    /**
     * @param  iterable<int, Tool>  $tools
     */
    public static function promptWithMemory(
        User $user,
        ?string $conversationId,
        string $message,
        string $instructions,
        iterable $tools,
    ): AgentResponse {
        $agent = new ConversationalOfficeAgent($instructions, $tools);

        if ($conversationId !== null) {
            $agent->continue($conversationId, $user);
        } else {
            $agent->forUser($user);
        }

        return $agent->prompt($message);
    }

    /**
     * @param  iterable<int, Tool>  $tools
     */
    public static function streamWithMemory(
        User $user,
        ?string $conversationId,
        string $message,
        string $instructions,
        iterable $tools,
    ): StreamableAgentResponse {
        $agent = new ConversationalOfficeAgent($instructions, $tools);

        if ($conversationId !== null) {
            $agent->continue($conversationId, $user);
        } else {
            $agent->forUser($user);
        }

        return $agent->stream($message);
    }
}
