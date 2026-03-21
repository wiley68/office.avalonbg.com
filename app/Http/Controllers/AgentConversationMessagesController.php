<?php

namespace App\Http\Controllers;

use App\Enums\AgentContext;
use App\Http\Requests\StoreAgentMessageFeedbackRequest;
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
            'conversations' => $rows->map(fn ($row) => [
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
            ->from('agent_conversation_messages as m')
            ->leftJoin('agent_message_feedback as f', function ($join) use ($userId) {
                $join->on('f.message_id', '=', 'm.id')
                    ->where('f.user_id', '=', $userId);
            })
            ->where('m.conversation_id', $conversation)
            ->whereIn('m.role', ['user', 'assistant'])
            ->orderBy('m.created_at')
            ->orderBy('m.id')
            ->get(['m.id', 'm.role', 'm.content', 'm.created_at', 'f.feedback']);

        return response()->json([
            'messages' => $rows->map(fn ($row) => [
                'id' => $row->id,
                'role' => $row->role,
                'content' => $row->content,
                'created_at' => $row->created_at,
                'feedback' => $row->feedback,
            ])->values()->all(),
        ]);
    }

    public function feedback(
        StoreAgentMessageFeedbackRequest $request,
        string $message,
    ): JsonResponse {
        $userId = $request->user()->id;
        $context = $request->expectedAgentContext();
        $feedback = $request->validated('feedback');

        $allowed = DB::table('agent_conversation_messages as m')
            ->join('agent_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->where('m.id', $message)
            ->where('m.role', 'assistant')
            ->where('c.user_id', $userId)
            ->where('c.context', $context->value)
            ->exists();

        if (! $allowed) {
            abort(404);
        }

        DB::table('agent_message_feedback')->upsert(
            [[
                'message_id' => $message,
                'user_id' => $userId,
                'feedback' => $feedback,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['message_id', 'user_id'],
            ['feedback', 'updated_at'],
        );

        return response()->json([
            'ok' => true,
            'feedback' => $feedback,
        ]);
    }

    private function contextFromRequest(Request $request): AgentContext
    {
        if ($request->routeIs('dashboard.agent.conversations', 'dashboard.agent.conversation.messages')) {
            return AgentContext::Orchestrator;
        }

        if ($request->routeIs('dashboard.notes.agent.conversations', 'dashboard.notes.agent.conversation.messages', 'dashboard.notes.agent.message.feedback')) {
            return AgentContext::Notes;
        }

        if ($request->routeIs('dashboard.agent.message.feedback')) {
            return AgentContext::Orchestrator;
        }

        throw new \LogicException(
            'Unknown agent conversation route: '.$request->route()?->getName()
        );
    }
}
