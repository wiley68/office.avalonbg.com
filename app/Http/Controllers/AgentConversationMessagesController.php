<?php

namespace App\Http\Controllers;

use App\Enums\AgentContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentConversationMessagesController extends Controller
{
    /**
     * Списък с разговори (id, title, updated_at) за текущия потребител и контекст на страницата.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $context = $this->contextFromRequest($request);

        $rows = DB::table('agent_conversations')
            ->where('user_id', $userId)
            ->where('context', $context->value)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'updated_at']);

        return response()->json([
            'conversations' => $rows->map(fn($row) => [
                'id' => $row->id,
                'title' => $row->title,
                'updated_at' => $row->updated_at,
            ])->values()->all(),
        ]);
    }

    /**
     * Съобщения от agent_conversation_messages за разговор на текущия потребител.
     */
    public function show(Request $request, string $conversation): JsonResponse
    {
        $userId = $request->user()->id;
        $context = $this->contextFromRequest($request);

        $owns = DB::table('agent_conversations')
            ->where('id', $conversation)
            ->where('user_id', $userId)
            ->where('context', $context->value)
            ->exists();

        if (! $owns) {
            abort(404);
        }

        $rows = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversation)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'role', 'content', 'created_at']);

        return response()->json([
            'messages' => $rows->map(fn($row) => [
                'id' => $row->id,
                'role' => $row->role,
                'content' => $row->content,
                'created_at' => $row->created_at,
            ])->values()->all(),
        ]);
    }

    private function contextFromRequest(Request $request): AgentContext
    {
        if ($request->routeIs('dashboard.agent.conversations', 'dashboard.agent.conversation.messages')) {
            return AgentContext::Orchestrator;
        }

        if ($request->routeIs('dashboard.notes.agent.conversations', 'dashboard.notes.agent.conversation.messages')) {
            return AgentContext::Notes;
        }

        throw new \LogicException(
            'Unknown agent conversation route: ' . $request->route()?->getName()
        );
    }
}
