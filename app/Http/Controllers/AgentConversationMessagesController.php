<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentConversationMessagesController extends Controller
{
    /**
     * Съобщения от agent_conversation_messages за разговор на текущия потребител.
     */
    public function show(Request $request, string $conversation): JsonResponse
    {
        $userId = $request->user()->id;

        $owns = DB::table('agent_conversations')
            ->where('id', $conversation)
            ->where('user_id', $userId)
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
            'messages' => $rows->map(fn ($row) => [
                'id' => $row->id,
                'role' => $row->role,
                'content' => $row->content,
                'created_at' => $row->created_at,
            ])->values()->all(),
        ]);
    }
}
