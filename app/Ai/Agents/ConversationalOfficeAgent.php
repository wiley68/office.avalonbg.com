<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Агент с история в agent_conversations (RemembersConversations + DatabaseConversationStore).
 *
 * По-висок max_tokens: при xAI/Grok reasoning моделите част от лимита отива за reasoning;
 * подразбираните ~2048 от Prism често „отрязват“ дълъг видим текст преди края на SSE.
 */
#[MaxTokens(8192)]
class ConversationalOfficeAgent implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;

    /**
     * @param  iterable<int, Tool>  $tools
     */
    public function __construct(
        public string $instructions,
        public iterable $tools,
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->instructions;
    }

    /**
     * @return iterable<int, Tool>
     */
    public function tools(): iterable
    {
        return $this->tools;
    }
}
