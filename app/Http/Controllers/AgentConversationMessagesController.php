<?php

namespace App\Http\Controllers;

use App\Enums\AgentContext;
use App\Http\Requests\StoreAgentMessageEmailRequest;
use App\Http\Requests\StoreAgentMessageFeedbackRequest;
use App\Services\AgentMessageEmailService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
            'messages' => $rows->map(fn($row) => [
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
            [
                [
                    'message_id' => $message,
                    'user_id' => $userId,
                    'feedback' => $feedback,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            ['message_id', 'user_id'],
            ['feedback', 'updated_at'],
        );

        return response()->json([
            'ok' => true,
            'feedback' => $feedback,
        ]);
    }

    public function email(
        StoreAgentMessageEmailRequest $request,
        AgentMessageEmailService $emailService,
        string $message,
    ): JsonResponse {
        $validated = $request->validated();
        try {
            $emailService->send(
                user: $request->user(),
                context: $request->expectedAgentContext(),
                email: $validated['email'],
                messageId: $message,
                subject: $validated['subject'] ?? null,
            );
        } catch (\RuntimeException) {
            abort(404);
        }

        return response()->json([
            'ok' => true,
        ]);
    }

    public function pdf(
        Request $request,
        AgentMessageEmailService $emailService,
        string $message,
    ): Response {
        $context = match (true) {
            $request->routeIs('dashboard.agent.message.pdf') => AgentContext::Orchestrator,
            $request->routeIs('dashboard.notes.agent.message.pdf') => AgentContext::Notes,
            $request->routeIs('dashboard.contacts.agent.message.pdf') => AgentContext::Contacts,
            $request->routeIs('dashboard.warranties.agent.message.pdf') => AgentContext::Warranties,
            default => throw new \LogicException(
                'Unexpected route for agent PDF: ' . $request->route()?->getName()
            ),
        };

        $content = $emailService->assistantMessageContentForUser(
            $request->user(),
            $context,
            $message,
        );

        if ($content === null) {
            abort(404);
        }

        $pdf = Pdf::loadView('pdf.agent-response', [
            'content' => $content,
            'appName' => config('app.name'),
            'generatedAt' => now()->timezone(config('app.timezone'))->format('d.m.Y H:i'),
        ]);

        return $pdf->stream('ofis-agent-otgovor.pdf');
    }

    /**
     * Изтрива всички разговори за текущия потребител и контекста на страницата (оркестратор / бележки),
     * заедно със съобщенията и обратната връзка към тях.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $context = match (true) {
            $request->routeIs('dashboard.agent.conversations.destroy') => AgentContext::Orchestrator,
            $request->routeIs('dashboard.notes.agent.conversations.destroy') => AgentContext::Notes,
            $request->routeIs('dashboard.contacts.agent.conversations.destroy') => AgentContext::Contacts,
            $request->routeIs('dashboard.warranties.agent.conversations.destroy') => AgentContext::Warranties,
            default => throw new \LogicException(
                'Unexpected route for destroy all conversations: ' . $request->route()?->getName()
            ),
        };

        $userId = $request->user()->id;

        $conversationIds = DB::table('agent_conversations')
            ->where('user_id', $userId)
            ->where('context', $context->value)
            ->pluck('id');

        if ($conversationIds->isEmpty()) {
            return response()->json([
                'ok' => true,
                'deleted_conversations' => 0,
            ]);
        }

        $messageIds = DB::table('agent_conversation_messages')
            ->whereIn('conversation_id', $conversationIds)
            ->pluck('id');

        DB::transaction(function () use ($messageIds, $conversationIds): void {
            if ($messageIds->isNotEmpty()) {
                DB::table('agent_message_feedback')->whereIn('message_id', $messageIds)->delete();
            }

            DB::table('agent_conversation_messages')->whereIn('conversation_id', $conversationIds)->delete();
            DB::table('agent_conversations')->whereIn('id', $conversationIds)->delete();
        });

        return response()->json([
            'ok' => true,
            'deleted_conversations' => $conversationIds->count(),
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

        if ($request->routeIs('dashboard.contacts.agent.conversations', 'dashboard.contacts.agent.conversation.messages', 'dashboard.contacts.agent.message.feedback')) {
            return AgentContext::Contacts;
        }

        if ($request->routeIs('dashboard.warranties.agent.conversations', 'dashboard.warranties.agent.conversation.messages', 'dashboard.warranties.agent.message.feedback')) {
            return AgentContext::Warranties;
        }

        if ($request->routeIs('dashboard.agent.message.feedback')) {
            return AgentContext::Orchestrator;
        }

        throw new \LogicException(
            'Unknown agent conversation route: ' . $request->route()?->getName()
        );
    }
}
