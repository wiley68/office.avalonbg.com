<?php

namespace App\Ai\Storage;

use App\Support\CurrentAgentContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Laravel\Ai\Storage\DatabaseConversationStore;

class ContextualDatabaseConversationStore extends DatabaseConversationStore
{
    public function storeConversation(string|int|null $userId, string $title): string
    {
        $context = CurrentAgentContext::get();

        if ($context === null) {
            throw new \RuntimeException('Agent conversation context is not set.');
        }

        $conversationId = (string) Str::uuid7();

        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => $userId,
            'title' => $title,
            'context' => $context->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $conversationId;
    }

    public function latestConversationId(string|int $userId): ?string
    {
        $query = DB::table('agent_conversations')->where('user_id', $userId);

        $context = CurrentAgentContext::get();

        if ($context !== null) {
            $query->where('context', $context->value);
        }

        return $query->orderByDesc('updated_at')->first()?->id;
    }

    /**
     * OpenAI Responses API изисква ненулев string за `call_id` при function_call / function_call_output.
     * Стари редове (напр. след xAI) може да имат `result_id` null — тогава Prism изпраща null и OpenAI връща 400.
     * Използваме `id` на tool call/result като резервен call_id (същото като при пълно записани OpenAI отговори).
     *
     * @return Collection<int, Message>
     */
    public function getLatestConversationMessages(string $conversationId, int $limit): Collection
    {
        return parent::getLatestConversationMessages($conversationId, $limit)
            ->map(function ($message) {
                if ($message instanceof AssistantMessage && $message->toolCalls->isNotEmpty()) {
                    $toolCalls = $message->toolCalls->map(function (ToolCall $tc): ToolCall {
                        if ($tc->resultId !== null && $tc->resultId !== '') {
                            return $tc;
                        }

                        if ($tc->id === '') {
                            return $tc;
                        }

                        return new ToolCall(
                            id: $tc->id,
                            name: $tc->name,
                            arguments: $tc->arguments,
                            resultId: $tc->id,
                            reasoningId: $tc->reasoningId,
                            reasoningSummary: $tc->reasoningSummary,
                        );
                    });

                    return new AssistantMessage($message->content, $toolCalls);
                }

                if ($message instanceof ToolResultMessage && $message->toolResults->isNotEmpty()) {
                    $toolResults = $message->toolResults->map(function (ToolResult $tr): ToolResult {
                        if ($tr->resultId !== null && $tr->resultId !== '') {
                            return $tr;
                        }

                        if ($tr->id === '') {
                            return $tr;
                        }

                        return new ToolResult(
                            id: $tr->id,
                            name: $tr->name,
                            arguments: $tr->arguments,
                            result: $tr->result,
                            resultId: $tr->id,
                        );
                    });

                    return new ToolResultMessage($toolResults);
                }

                return $message;
            });
    }
}
