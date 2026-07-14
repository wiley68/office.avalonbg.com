<?php

namespace App\Services;

use App\Exceptions\ImapConnectionException;
use App\Models\UserImapAccount;
use App\Support\EmailConversation;
use App\Support\Translations;
use IMAP\Connection;

class ImapMailboxService
{
    public function __construct(
        private readonly EmailConversation $emailConversation,
    ) {}

    /**
     * @throws ImapConnectionException
     */
    public function folderUidValidity(UserImapAccount $account, string $folder): int
    {
        return $this->withConnection($account, $folder, function ($connection, string $mailbox): int {
            return $this->readFolderUidValidity($connection, $mailbox);
        });
    }

    /**
     * @throws ImapConnectionException
     */
    public function testConnection(UserImapAccount $account): void
    {
        $this->withConnection($account, $account->default_folder, function ($connection): void {
            imap_check($connection);
        });

        $account->update(['last_verified_at' => now()]);
    }

    /**
     * @return list<array{
     *     name: string,
     *     path: string,
     *     children: list<array<string, mixed>>
     * }>
     *
     * @throws ImapConnectionException
     */
    public function listFolderTree(UserImapAccount $account): array
    {
        return $this->withConnection($account, $account->default_folder, function ($connection) use ($account): array {
            $reference = $account->mailboxReference();
            $mailboxes = imap_getmailboxes($connection, $reference, '*') ?: [];

            if ($mailboxes === []) {
                return [[
                    'name' => $account->default_folder,
                    'path' => $account->default_folder,
                    'children' => [],
                ]];
            }

            $delimiter = $mailboxes[0]->delimiter ?? '.';
            $paths = [];

            foreach ($mailboxes as $mailbox) {
                if (! isset($mailbox->name) || ! is_string($mailbox->name)) {
                    continue;
                }

                $decodedName = imap_utf7_decode($mailbox->name);
                $path = str_starts_with($decodedName, $reference)
                    ? substr($decodedName, strlen($reference))
                    : $decodedName;

                if ($path !== '') {
                    $paths[] = $path;
                }
            }

            $paths = array_values(array_unique($paths));
            sort($paths);

            return $this->buildFolderTree($paths, $delimiter);
        });
    }

    /**
     * @param  list<string>  $paths
     * @return list<array{
     *     name: string,
     *     path: string,
     *     children: list<array<string, mixed>>
     * }>
     */
    private function buildFolderTree(array $paths, string $delimiter): array
    {
        /** @var array<string, array{name: string, path: string, children: array<string, mixed>}> $nodes */
        $nodes = [];

        foreach ($paths as $path) {
            $parts = explode($delimiter, $path);
            $currentPath = '';

            foreach ($parts as $index => $part) {
                if ($part === '') {
                    continue;
                }

                $currentPath = $index === 0 ? $part : $currentPath.$delimiter.$part;

                if (! array_key_exists($currentPath, $nodes)) {
                    $nodes[$currentPath] = [
                        'name' => $part,
                        'path' => $currentPath,
                        'children' => [],
                    ];
                }
            }
        }

        /** @var array<string, list<array{name: string, path: string, children: list<array<string, mixed>>}>> $childrenByParent */
        $childrenByParent = ['' => []];

        foreach ($nodes as $path => $node) {
            $parentPath = str_contains($path, $delimiter)
                ? substr($path, 0, (int) strrpos($path, $delimiter))
                : '';

            $childrenByParent[$parentPath] ??= [];
            $childrenByParent[$parentPath][] = [
                'name' => $node['name'],
                'path' => $node['path'],
                'children' => [],
            ];
        }

        $attachChildren = function (array $node) use (&$attachChildren, $childrenByParent): array {
            $childNodes = $childrenByParent[$node['path']] ?? [];

            usort(
                $childNodes,
                fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']),
            );

            $node['children'] = array_map(
                fn (array $child): array => $attachChildren($child),
                $childNodes,
            );

            return $node;
        };

        $roots = $childrenByParent[''] ?? [];

        usort(
            $roots,
            fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']),
        );

        return array_map(
            fn (array $root): array => $attachChildren($root),
            $roots,
        );
    }

    /**
     * @return list<array{
     *     id: string,
     *     grouping: 'imap_thread'|'subject'|'merged'|'conversation',
     *     message_count: int,
     *     subject: string,
     *     latest_sent_at: string|null,
     *     messages: list<array{
     *         uid: int,
     *         uidvalidity: int,
     *         subject: string,
     *         from_name: string|null,
     *         from_address: string|null,
     *         sent_at: string|null
     *     }>
     * }>
     *
     * @throws ImapConnectionException
     */
    public function browseMessageThreads(
        UserImapAccount $account,
        ?string $folder = null,
        int $limit = 100,
        string $search = '',
    ): array {
        $folder = $folder ?: $account->default_folder;
        $limit = min(max($limit, 1), 100);

        return $this->withConnection($account, $folder, function ($connection, string $mailbox) use ($limit, $search): array {
            $uidvalidity = $this->readFolderUidValidity($connection, $mailbox);
            $uids = $this->resolveMessageUids($connection, $limit, $search);

            if ($uids === []) {
                return [];
            }

            $messagesByUid = $this->fetchMessagesByUid($connection, $uids, $uidvalidity);
            $imapGroups = $this->groupUidsByImapThread($connection, array_keys($messagesByUid));
            $groupedUids = [];

            foreach ($imapGroups as $groupUids) {
                foreach ($groupUids as $uid) {
                    $groupedUids[$uid] = true;
                }
            }

            $threads = [];

            foreach ($imapGroups as $rootUid => $groupUids) {
                $threadMessages = $this->messagesForUids($messagesByUid, $groupUids);

                if ($threadMessages === []) {
                    continue;
                }

                $threads[] = $this->formatThread(
                    id: 'imap-'.$rootUid,
                    grouping: 'imap_thread',
                    messages: $threadMessages,
                );
            }

            $remainingUids = array_values(array_filter(
                array_keys($messagesByUid),
                fn (int $uid): bool => ! isset($groupedUids[$uid]),
            ));

            foreach ($this->groupUidsByNormalizedSubject($remainingUids, $messagesByUid) as $subjectKey => $groupUids) {
                $threadMessages = $this->messagesForUids($messagesByUid, $groupUids);

                if ($threadMessages === []) {
                    continue;
                }

                $threads[] = $this->formatThread(
                    id: 'subject-'.$subjectKey,
                    grouping: 'subject',
                    messages: $threadMessages,
                );
            }

            $threads = $this->mergeThreadsByConversationKey($threads);

            usort(
                $threads,
                fn (array $left, array $right): int => strtotime($right['latest_sent_at'] ?? '') <=> strtotime($left['latest_sent_at'] ?? ''),
            );

            return $threads;
        });
    }

    /**
     * @return list<array{
     *     uid: int,
     *     uidvalidity: int,
     *     subject: string,
     *     from_name: string|null,
     *     from_address: string|null,
     *     sent_at: string|null
     * }>
     *
     * @throws ImapConnectionException
     */
    public function browseRecentMessages(
        UserImapAccount $account,
        ?string $folder = null,
        int $limit = 50,
        string $search = '',
    ): array {
        $folder = $folder ?: $account->default_folder;
        $limit = min(max($limit, 1), 100);

        return $this->withConnection($account, $folder, function ($connection, string $mailbox) use ($limit, $search): array {
            $uidvalidity = $this->readFolderUidValidity($connection, $mailbox);
            $uids = $this->resolveMessageUids($connection, $limit, $search);

            if ($uids === []) {
                return [];
            }

            $overviews = imap_fetch_overview($connection, implode(',', $uids), FT_UID) ?: [];

            usort(
                $overviews,
                fn (object $left, object $right): int => strtotime($right->date ?? '') <=> strtotime($left->date ?? ''),
            );

            return array_map(
                fn (object $overview): array => $this->mapOverview($overview, $uidvalidity),
                $overviews,
            );
        });
    }

    /**
     * @return list<int>
     */
    private function resolveMessageUids(Connection $connection, int $limit, string $search): array
    {
        if ($search !== '') {
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $search);
            $searchCriteria = 'TEXT "'.$escaped.'"';
            $matchedUids = imap_search($connection, $searchCriteria, SE_UID);

            if ($matchedUids === false || $matchedUids === []) {
                return [];
            }

            rsort($matchedUids, SORT_NUMERIC);

            return array_slice($matchedUids, 0, $limit);
        }

        $check = imap_check($connection);

        if ($check === false || $check->Nmsgs === 0) {
            return [];
        }

        $start = max(1, $check->Nmsgs - $limit + 1);
        $sequence = $start.':'.$check->Nmsgs;
        $overviews = imap_fetch_overview($connection, $sequence) ?: [];

        return array_values(array_filter(array_map(
            fn (object $overview): int => (int) ($overview->uid ?? 0),
            $overviews,
        )));
    }

    /**
     * @return array{
     *     uid: int,
     *     uidvalidity: int,
     *     subject: string,
     *     from_name: string|null,
     *     from_address: string|null,
     *     sent_at: string|null
     * }|null
     *
     * @throws ImapConnectionException
     */
    public function fetchMessageSummary(
        UserImapAccount $account,
        string $folder,
        int $uid,
    ): ?array {
        return $this->withConnection($account, $folder, function ($connection, string $mailbox) use ($uid): ?array {
            $uidvalidity = $this->readFolderUidValidity($connection, $mailbox);
            $overview = imap_fetch_overview($connection, (string) $uid, FT_UID);

            if ($overview === false || $overview === []) {
                return null;
            }

            return $this->mapOverview($overview[0], $uidvalidity);
        });
    }

    /**
     * @return array{text: string|null, html: string|null}
     *
     * @throws ImapConnectionException
     */
    public function fetchMessageBody(
        UserImapAccount $account,
        string $folder,
        int $uid,
    ): array {
        return $this->withConnection($account, $folder, function ($connection) use ($uid): array {
            $structure = imap_fetchstructure($connection, $uid, FT_UID);

            if ($structure === false) {
                return ['text' => null, 'html' => null];
            }

            return $this->extractBodies($connection, $uid, $structure);
        });
    }

    /**
     * @param  callable(Connection, string): mixed  $callback
     *
     * @throws ImapConnectionException
     */
    private function withConnection(UserImapAccount $account, string $folder, callable $callback): mixed
    {
        if (! function_exists('imap_open')) {
            throw new ImapConnectionException(Translations::get('settings.email.errors.extension_missing'));
        }

        if ($account->encryption->value === 'tls' && $account->port === 143) {
            throw new ImapConnectionException(Translations::get('settings.email.errors.starttls_unsupported'));
        }

        $mailbox = $account->mailboxPath($folder);
        $connection = @imap_open(
            $mailbox,
            $account->username,
            $account->password,
            OP_READONLY,
        );

        if ($connection === false) {
            throw new ImapConnectionException($this->lastImapError());
        }

        try {
            return $callback($connection, $mailbox);
        } finally {
            imap_close($connection);
        }
    }

    private function readFolderUidValidity(Connection $connection, string $mailbox): int
    {
        $status = imap_status($connection, $mailbox, SA_UIDVALIDITY);

        return $status !== false ? (int) $status->uidvalidity : 0;
    }

    /**
     * @return array{
     *     uid: int,
     *     uidvalidity: int,
     *     subject: string,
     *     from_name: string|null,
     *     from_address: string|null,
     *     sent_at: string|null
     * }
     */
    private function mapOverview(object $overview, int $uidvalidity): array
    {
        [$fromName, $fromAddress] = $this->parseFromAddress($overview->from ?? '');

        $subject = isset($overview->subject)
            ? $this->decodeHeader((string) $overview->subject)
            : '';

        $sentAt = null;

        if (isset($overview->date) && is_string($overview->date) && $overview->date !== '') {
            $timestamp = strtotime($overview->date);

            if ($timestamp !== false) {
                $sentAt = date('c', $timestamp);
            }
        }

        return [
            'uid' => (int) ($overview->uid ?? 0),
            'uidvalidity' => $uidvalidity,
            'subject' => $subject,
            'from_name' => $fromName,
            'from_address' => $fromAddress,
            'sent_at' => $sentAt,
        ];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function parseFromAddress(string $from): array
    {
        if ($from === '') {
            return [null, null];
        }

        if (preg_match('/^(?:"?([^"<]*)"?\s)?(?:<?([^>]+@[^>]+)>?)$/', trim($from), $matches) === 1) {
            $name = trim($matches[1] ?? '') ?: null;
            $address = trim($matches[2] ?? '') ?: null;

            return [$name, $address];
        }

        return [null, trim($from) ?: null];
    }

    private function decodeHeader(string $value): string
    {
        $decoded = imap_utf8($value);

        return $decoded !== false ? $decoded : $value;
    }

    /**
     * @return array{text: string|null, html: string|null}
     */
    private function extractBodies(Connection $connection, int $uid, object $structure, string $partNumber = ''): array
    {
        $text = null;
        $html = null;

        if ($structure->type === TYPETEXT && isset($structure->subtype)) {
            $body = $this->fetchPartBody($connection, $uid, $partNumber === '' ? '1' : $partNumber, $structure);
            $subtype = strtoupper($structure->subtype);

            if ($subtype === 'PLAIN') {
                $text = $body;
            }

            if ($subtype === 'HTML') {
                $html = $body;
            }

            return ['text' => $text, 'html' => $html];
        }

        if (! isset($structure->parts) || ! is_array($structure->parts)) {
            return ['text' => null, 'html' => null];
        }

        foreach ($structure->parts as $index => $part) {
            $nextPartNumber = $partNumber === ''
                ? (string) ($index + 1)
                : $partNumber.'.'.($index + 1);

            $partBodies = $this->extractBodies($connection, $uid, $part, $nextPartNumber);

            if ($text === null && $partBodies['text'] !== null) {
                $text = $partBodies['text'];
            }

            if ($html === null && $partBodies['html'] !== null) {
                $html = $partBodies['html'];
            }
        }

        return ['text' => $text, 'html' => $html];
    }

    private function fetchPartBody(
        Connection $connection,
        int $uid,
        string $partNumber,
        object $structure,
    ): ?string {
        $body = imap_fetchbody($connection, $uid, $partNumber, FT_UID);

        if ($body === false) {
            return null;
        }

        return $this->decodeBody($body, (int) ($structure->encoding ?? ENCBASE64));
    }

    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            ENCBASE64 => base64_decode($body, true) ?: $body,
            ENCQUOTEDPRINTABLE => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function lastImapError(): string
    {
        $errors = imap_errors();
        imap_alerts();

        if (is_array($errors) && $errors !== []) {
            return (string) $errors[0];
        }

        return Translations::get('settings.email.errors.connection_failed');
    }

    /**
     * @param  list<int>  $uids
     * @return array<int, array{
     *     uid: int,
     *     uidvalidity: int,
     *     subject: string,
     *     from_name: string|null,
     *     from_address: string|null,
     *     sent_at: string|null
     * }>
     */
    private function fetchMessagesByUid(Connection $connection, array $uids, int $uidvalidity): array
    {
        $overviews = imap_fetch_overview($connection, implode(',', $uids), FT_UID) ?: [];
        $messagesByUid = [];

        foreach ($overviews as $overview) {
            $message = $this->mapOverview($overview, $uidvalidity);

            if ($message['uid'] > 0) {
                $messagesByUid[$message['uid']] = $message;
            }
        }

        return $messagesByUid;
    }

    /**
     * @param  list<int>  $uids
     * @return array<int, list<int>>
     */
    private function groupUidsByImapThread(Connection $connection, array $uids): array
    {
        if ($uids === []) {
            return [];
        }

        $threadData = @imap_thread($connection, SE_UID);

        if ($threadData === false || ! is_string($threadData) || trim($threadData) === '') {
            return [];
        }

        $parentOf = $this->parseImapThreadParents($threadData);
        $groups = [];

        foreach ($uids as $uid) {
            if (! array_key_exists($uid, $parentOf)) {
                continue;
            }

            $rootUid = $this->resolveThreadRootUid($uid, $parentOf);
            $groups[$rootUid] ??= [];
            $groups[$rootUid][] = $uid;
        }

        foreach ($groups as $rootUid => $groupUids) {
            $groups[$rootUid] = array_values(array_unique($groupUids));
        }

        return array_filter(
            $groups,
            fn (array $groupUids): bool => $groupUids !== [],
        );
    }

    /**
     * @return array<int, int|null>
     */
    private function parseImapThreadParents(string $threadData): array
    {
        $parentOf = [];

        foreach (preg_split('/\R/', trim($threadData)) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $line) ?: [];
            $uid = (int) ($parts[0] ?? 0);

            if ($uid <= 0) {
                continue;
            }

            $parentOf[$uid] = isset($parts[1]) ? (int) $parts[1] : null;
        }

        return $parentOf;
    }

    /**
     * @param  array<int, int|null>  $parentOf
     */
    private function resolveThreadRootUid(int $uid, array $parentOf): int
    {
        $visited = [];

        while (array_key_exists($uid, $parentOf) && $parentOf[$uid] !== null) {
            if (isset($visited[$uid])) {
                break;
            }

            $visited[$uid] = true;
            $uid = $parentOf[$uid];
        }

        return $uid;
    }

    /**
     * @param  list<int>  $uids
     * @param  array<int, array{
     *     uid: int,
     *     uidvalidity: int,
     *     subject: string,
     *     from_name: string|null,
     *     from_address: string|null,
     *     sent_at: string|null
     * }>  $messagesByUid
     * @return array<string, list<int>>
     */
    private function groupUidsByNormalizedSubject(array $uids, array $messagesByUid): array
    {
        $groups = [];

        foreach ($uids as $uid) {
            if (! array_key_exists($uid, $messagesByUid)) {
                continue;
            }

            $normalized = $this->emailConversation->normalizeSubject($messagesByUid[$uid]['subject']);
            $key = $normalized !== '' ? md5($normalized) : 'uid-'.$uid;
            $groups[$key] ??= [];
            $groups[$key][] = $uid;
        }

        return $groups;
    }

    /**
     * @param  list<array{
     *     id: string,
     *     grouping: string,
     *     message_count: int,
     *     subject: string,
     *     latest_sent_at: string|null,
     *     messages: list<array<string, mixed>>
     * }>  $threads
     * @return list<array{
     *     id: string,
     *     grouping: string,
     *     message_count: int,
     *     subject: string,
     *     latest_sent_at: string|null,
     *     messages: list<array<string, mixed>>
     * }>
     */
    private function mergeThreadsByConversationKey(array $threads): array
    {
        /** @var array<string, array{messages: array<int, array<string, mixed>>, source_ids: list<string>, groupings: list<string>}> $buckets */
        $buckets = [];

        foreach ($threads as $thread) {
            $key = $this->emailConversation->conversationKey($thread['subject']);
            $buckets[$key] ??= [
                'messages' => [],
                'source_ids' => [],
                'groupings' => [],
            ];

            foreach ($thread['messages'] as $message) {
                $buckets[$key]['messages'][(int) $message['uid']] = $message;
            }

            $buckets[$key]['source_ids'][] = $thread['id'];
            $buckets[$key]['groupings'][] = $thread['grouping'];
        }

        $mergedThreads = [];

        foreach ($buckets as $key => $bucket) {
            $messages = array_values($bucket['messages']);

            usort(
                $messages,
                fn (array $left, array $right): int => strtotime($left['sent_at'] ?? '') <=> strtotime($right['sent_at'] ?? ''),
            );

            $uniqueSources = array_values(array_unique($bucket['source_ids']));
            $grouping = count($uniqueSources) > 1
                ? 'merged'
                : ($bucket['groupings'][0] ?? 'conversation');

            $mergedThreads[] = $this->formatThread(
                id: 'conversation-'.str_replace(':', '-', $key),
                grouping: $grouping,
                messages: $messages,
            );
        }

        return $mergedThreads;
    }

    /**
     * @param  array<int, array{
     *     uid: int,
     *     uidvalidity: int,
     *     subject: string,
     *     from_name: string|null,
     *     from_address: string|null,
     *     sent_at: string|null
     * }>  $messagesByUid
     * @param  list<int>  $uids
     * @return list<array{
     *     uid: int,
     *     uidvalidity: int,
     *     subject: string,
     *     from_name: string|null,
     *     from_address: string|null,
     *     sent_at: string|null
     * }>
     */
    private function messagesForUids(array $messagesByUid, array $uids): array
    {
        $messages = [];

        foreach ($uids as $uid) {
            if (array_key_exists($uid, $messagesByUid)) {
                $messages[] = $messagesByUid[$uid];
            }
        }

        usort(
            $messages,
            fn (array $left, array $right): int => strtotime($left['sent_at'] ?? '') <=> strtotime($right['sent_at'] ?? ''),
        );

        return $messages;
    }

    /**
     * @param  list<array{
     *     uid: int,
     *     uidvalidity: int,
     *     subject: string,
     *     from_name: string|null,
     *     from_address: string|null,
     *     sent_at: string|null
     * }>  $messages
     * @return array{
     *     id: string,
     *     grouping: 'imap_thread'|'subject'|'merged'|'conversation',
     *     message_count: int,
     *     subject: string,
     *     latest_sent_at: string|null,
     *     messages: list<array{
     *         uid: int,
     *         uidvalidity: int,
     *         subject: string,
     *         from_name: string|null,
     *         from_address: string|null,
     *         sent_at: string|null
     *     }>
     * }
     */
    private function formatThread(string $id, string $grouping, array $messages): array
    {
        $root = $messages[0];
        $latest = $messages[array_key_last($messages)];

        return [
            'id' => $id,
            'grouping' => $grouping,
            'message_count' => count($messages),
            'subject' => $root['subject'] !== '' ? $root['subject'] : ($latest['subject'] !== '' ? $latest['subject'] : ''),
            'latest_sent_at' => $latest['sent_at'],
            'messages' => $messages,
        ];
    }
}
