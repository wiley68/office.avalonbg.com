<?php

namespace App\Support;

class EmailConversation
{
    public function normalizeSubject(string $subject): string
    {
        $normalized = trim($subject);

        do {
            $previous = $normalized;
            $normalized = preg_replace(
                '/^(?:(?:re|fwd?|fw|aw|sv|antw|ответ|относно)(?:\[\d+\])?\s*:\s*)+/iu',
                '',
                $normalized,
            ) ?? $normalized;
        } while ($normalized !== $previous && $normalized !== '');

        $normalized = preg_replace('/\s+/u', ' ', $normalized ?? '');

        return mb_strtolower(trim($normalized ?? ''));
    }

    public function conversationKey(string $subject): string
    {
        if (preg_match('/#(\d+)/u', $subject, $matches) === 1) {
            return 'ticket:#'.$matches[1];
        }

        if (preg_match('/\b(dem-\d+)\b/iu', $subject, $matches) === 1) {
            return 'ticket:'.mb_strtolower($matches[1]);
        }

        $normalized = $this->normalizeSubject($subject);

        return $normalized !== '' ? 'subject:'.md5($normalized) : 'subject:empty';
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{
     *     id: string,
     *     message_count: int,
     *     subject: string,
     *     latest_sent_at: string|null,
     *     messages: list<array<string, mixed>>
     * }>
     */
    public function groupIntoThreads(array $items, callable $subjectResolver): array
    {
        /** @var array<string, list<array<string, mixed>>> $buckets */
        $buckets = [];

        foreach ($items as $item) {
            $subject = (string) $subjectResolver($item);
            $key = $this->conversationKey($subject);
            $buckets[$key] ??= [];
            $buckets[$key][] = $item;
        }

        $threads = [];

        foreach ($buckets as $key => $messages) {
            usort(
                $messages,
                fn (array $left, array $right): int => strtotime((string) ($left['sent_at'] ?? '')) <=> strtotime((string) ($right['sent_at'] ?? '')),
            );

            $latestMessage = $messages !== [] ? $messages[array_key_last($messages)] : null;

            $threads[] = [
                'id' => 'conversation-'.str_replace(':', '-', $key),
                'message_count' => count($messages),
                'subject' => (string) ($latestMessage['subject'] ?? ''),
                'latest_sent_at' => isset($latestMessage['sent_at']) ? (string) $latestMessage['sent_at'] : null,
                'messages' => $messages,
            ];
        }

        usort(
            $threads,
            fn (array $left, array $right): int => strtotime($right['latest_sent_at'] ?? '') <=> strtotime($left['latest_sent_at'] ?? ''),
        );

        return $threads;
    }
}
