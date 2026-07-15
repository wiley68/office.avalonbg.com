<?php

namespace App\Services;

use App\Enums\ProjectEmailLinkStatus;
use App\Models\Project;
use App\Models\ProjectEmailLink;
use App\Models\User;
use App\Models\UserImapAccount;
use App\Support\EmailConversation;

class ProjectEmailService
{
    public function __construct(
        private readonly ImapMailboxService $imapMailboxService,
        private readonly EmailConversation $emailConversation,
    ) {}

    public function accountForUser(User $user): ?UserImapAccount
    {
        return $user->imapAccount;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function refreshLinkThreads(Project $project, User $user): array
    {
        $links = $this->refreshLinks($project, $user);

        return $this->emailConversation->groupIntoThreads(
            $links,
            fn (array $link): string => (string) ($link['subject'] ?? ''),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function refreshLinks(Project $project, User $user): array
    {
        $account = $user->imapAccount;
        $folderUidValidities = [];

        $links = $project->emailLinks()
            ->with(['attachments.document'])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->get();

        if ($account !== null) {
            foreach ($links as $link) {
                if ($link->isArchived()) {
                    continue;
                }

                $this->verifyLink($account, $link, $folderUidValidities);
            }

            $links = $project->emailLinks()
                ->with(['attachments.document'])
                ->orderByDesc('sent_at')
                ->orderByDesc('id')
                ->get();
        }

        return $links
            ->map(fn (ProjectEmailLink $link): array => $this->formatLink($link))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatLink(ProjectEmailLink $link): array
    {
        return [
            'id' => $link->id,
            'folder' => $link->folder,
            'imap_uid' => $link->imap_uid,
            'uidvalidity' => $link->uidvalidity,
            'subject' => $link->subject,
            'from_name' => $link->from_name,
            'from_address' => $link->from_address,
            'sent_at' => $link->sent_at?->toIso8601String(),
            'status' => $link->isArchived()
                ? ProjectEmailLinkStatus::Active->value
                : $link->status->value,
            'is_archived' => $link->isArchived(),
            'conversation_key' => $link->conversation_key,
            'last_verified_at' => $link->last_verified_at?->toIso8601String(),
            'attachments' => $link->attachments
                ->map(fn ($attachment): array => [
                    'part' => $attachment->imap_part,
                    'filename' => $attachment->document->original_name,
                ])
                ->values()
                ->all(),
        ];
    }

    public function hasArchivedLinks(Project $project): bool
    {
        return $project->emailLinks()
            ->whereNotNull('archived_at')
            ->exists();
    }

    /**
     * @param  array<string, int>  $folderUidValidities
     */
    private function verifyLink(
        UserImapAccount $account,
        ProjectEmailLink $link,
        array &$folderUidValidities,
    ): void {
        if (! array_key_exists($link->folder, $folderUidValidities)) {
            try {
                $folderUidValidities[$link->folder] = $this->imapMailboxService->folderUidValidity(
                    $account,
                    $link->folder,
                );
            } catch (\Throwable) {
                $folderUidValidities[$link->folder] = 0;
            }
        }

        $currentUidValidity = $folderUidValidities[$link->folder];

        if ($currentUidValidity !== 0 && $currentUidValidity !== $link->uidvalidity) {
            $link->update([
                'status' => ProjectEmailLinkStatus::MissingOnServer,
                'last_verified_at' => now(),
            ]);

            return;
        }

        $summary = $this->imapMailboxService->fetchMessageSummary(
            $account,
            $link->folder,
            $link->imap_uid,
        );

        if ($summary === null) {
            $link->update([
                'status' => ProjectEmailLinkStatus::MissingOnServer,
                'last_verified_at' => now(),
            ]);

            return;
        }

        $link->update([
            'status' => ProjectEmailLinkStatus::Active,
            'uidvalidity' => $summary['uidvalidity'],
            'subject' => $summary['subject'],
            'from_name' => $summary['from_name'],
            'from_address' => $summary['from_address'],
            'sent_at' => $summary['sent_at'],
            'last_verified_at' => now(),
        ]);
    }
}
