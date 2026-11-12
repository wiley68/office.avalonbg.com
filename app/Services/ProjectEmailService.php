<?php

namespace App\Services;

use App\Enums\ProjectEmailLinkStatus;
use App\Models\Project;
use App\Models\ProjectEmailLink;
use App\Models\User;
use App\Models\UserImapAccount;
use App\Support\EmailConversation;
use App\Support\Translations;
use Illuminate\Support\Facades\Gate;

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

    public function accountForLink(User $user, ProjectEmailLink $link): UserImapAccount
    {
        $user = $user->fresh();
        abort_unless($user?->isActive(), 403);
        Gate::forUser($user)->authorize('view', $link->project);
        $account = $user->imapAccount;
        abort_unless($account !== null && $this->canReadLive($user, $account, $link), 403, Translations::get('projects.email.errors.mailbox_owner_required'));
        try {
            $validity = $this->imapMailboxService->folderUidValidity($account, $link->folder);
        } catch (\Throwable) {
            abort(422, Translations::get('projects.email.errors.archive_failed'));
        }
        abort_unless($validity > 0, 422, Translations::get('projects.email.errors.archive_failed'));
        abort_unless($validity === $link->uidvalidity, 404, Translations::get('projects.email.errors.missing_on_server'));

        return $account;
    }

    private function canReadLive(User $user, UserImapAccount $account, ProjectEmailLink $link): bool
    {
        return $link->user_id === $user->id && $account->user_id === $user->id
            && $link->source_mailbox !== null && hash_equals($link->source_mailbox, $account->mailboxIdentity());
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
        Gate::forUser($user)->authorize('view', $project);

        $account = $user->imapAccount;
        $folderUidValidities = [];

        $links = $project->emailLinks()
            ->with(['attachments.document'])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->get();

        if ($account !== null) {
            foreach ($links as $link) {
                if ($link->isArchived() || ! $this->canReadLive($user, $account, $link)) {
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
            ->map(fn (ProjectEmailLink $link): array => [...$this->formatLink($link), 'can_read' => $link->isArchived() || ($account !== null && $this->canReadLive($user, $account, $link))])
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
            'attachments' => $link->isArchived() ? $link->attachments
                ->map(fn ($attachment): array => [
                    'part' => $attachment->imap_part,
                    'filename' => $attachment->document->original_name,
                    'mime_type' => $attachment->document->mime_type,
                ])
                ->values()
                ->all() : [],
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
            $folderUidValidities[$link->folder] = $this->imapMailboxService->folderUidValidity(
                $account,
                $link->folder,
            );
        }

        $currentUidValidity = $folderUidValidities[$link->folder];

        abort_unless($currentUidValidity > 0, 422, Translations::get('projects.email.errors.archive_failed'));

        if ($currentUidValidity !== $link->uidvalidity) {
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

        if ($summary === null || $summary['uidvalidity'] !== $link->uidvalidity) {
            $link->update([
                'status' => ProjectEmailLinkStatus::MissingOnServer,
                'last_verified_at' => now(),
            ]);

            return;
        }

        $link->update([
            'status' => ProjectEmailLinkStatus::Active,
            'subject' => $summary['subject'],
            'from_name' => $summary['from_name'],
            'from_address' => $summary['from_address'],
            'sent_at' => $summary['sent_at'],
            'last_verified_at' => now(),
        ]);
    }
}
