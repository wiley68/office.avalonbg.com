<?php

namespace App\Ai\Storage;

use App\Support\CurrentAgentContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
}
