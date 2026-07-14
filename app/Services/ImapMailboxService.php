<?php

namespace App\Services;

use App\Exceptions\ImapConnectionException;
use App\Models\UserImapAccount;
use App\Support\Translations;
use IMAP\Connection;

class ImapMailboxService
{
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
    ): array {
        $folder = $folder ?: $account->default_folder;
        $limit = min(max($limit, 1), 100);

        return $this->withConnection($account, $folder, function ($connection, string $mailbox) use ($limit): array {
            $uidvalidity = $this->readFolderUidValidity($connection, $mailbox);
            $check = imap_check($connection);

            if ($check === false || $check->Nmsgs === 0) {
                return [];
            }

            $start = max(1, $check->Nmsgs - $limit + 1);
            $sequence = $start.':'.$check->Nmsgs;
            $overviews = imap_fetch_overview($connection, $sequence) ?: [];

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
}
