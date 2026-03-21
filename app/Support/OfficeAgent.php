<?php

namespace App\Support;

use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Responses\AgentResponse;

use function Laravel\Ai\agent;

/**
 * Общ вход за изпълнение на офис агент (оркестратор или специализиран).
 */
final class OfficeAgent
{
    /**
     * @param  iterable<int, Tool>  $tools
     */
    public static function prompt(string $message, string $instructions, iterable $tools): AgentResponse
    {
        return agent(
            instructions: $instructions,
            tools: $tools,
        )->prompt($message);
    }
}
