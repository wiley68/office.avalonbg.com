<?php

namespace App\Services;

use App\Enums\AgentContext;
use App\Mail\AgentResponseMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AgentMessageEmailService
{
    /**
     * Текст на assistant съобщение, ако принадлежи на потребителя и контекста.
     */
    public function assistantMessageContentForUser(
        User $user,
        AgentContext $context,
        string $messageId,
    ): ?string {
        $row = $this->assistantMessageByIdForContext($user, $context, $messageId);

        return $row?->content;
    }

    /**
     * @return array{id: string, content: string}
     */
    public function send(
        User $user,
        AgentContext $context,
        string $email,
        ?string $messageId = null,
        ?string $subject = null,
        ?string $content = null,
    ): array {
        $resolvedContent = $content;
        $resolvedId = $messageId;

        if ($resolvedContent === null || trim($resolvedContent) === '') {
            $message = $messageId === null
                ? $this->latestAssistantMessageForContext($user, $context)
                : $this->assistantMessageByIdForContext($user, $context, $messageId);

            if ($message === null) {
                throw new \RuntimeException('Не е намерен assistant отговор за изпращане.');
            }

            $resolvedContent = $message->content;
            $resolvedId = $message->id;
        }

        $mail = new AgentResponseMail(
            responseText: $resolvedContent,
            subjectText: $subject ?: 'Отговор от офис агента',
        );

        if (config('office.agent_email_delivery') === 'queue') {
            Mail::to($email)->queue($mail);
        } else {
            Mail::to($email)->send($mail);
        }

        return [
            'id' => $resolvedId ?: 'manual-content',
            'content' => $resolvedContent,
        ];
    }

    private function assistantMessageByIdForContext(
        User $user,
        AgentContext $context,
        string $messageId,
    ): ?object {
        return DB::table('agent_conversation_messages as m')
            ->join('agent_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->where('m.id', $messageId)
            ->where('m.role', 'assistant')
            ->where('c.user_id', $user->id)
            ->where('c.context', $context->value)
            ->first(['m.id', 'm.content']);
    }

    private function latestAssistantMessageForContext(
        User $user,
        AgentContext $context,
    ): ?object {
        return DB::table('agent_conversation_messages as m')
            ->join('agent_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->where('m.role', 'assistant')
            ->where('c.user_id', $user->id)
            ->where('c.context', $context->value)
            ->orderByDesc('m.created_at')
            ->orderByDesc('m.id')
            ->first(['m.id', 'm.content']);
    }
}
