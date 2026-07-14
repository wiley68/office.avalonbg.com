<?php

namespace App\Services;

use App\Enums\ProjectEmailLinkStatus;
use App\Models\Project;
use App\Models\ProjectEmailLink;
use App\Models\User;
use App\Models\UserImapAccount;
use App\Support\Translations;

class ProjectEmailService
{
    public function __construct(
        private readonly ImapMailboxService $imapMailboxService,
    ) {}

    public function accountForUser(User $user): ?UserImapAccount
    {
        return $user->imapAccount;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function refreshLinks(Project $project, User $user): array
    {
        $account = $this->requireAccount($user);

        $folderUidValidities = [];

        $links = $project->emailLinks()
            ->where('user_id', $user->id)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->get();

        foreach ($links as $link) {
            $this->verifyLink($account, $link, $folderUidValidities);
        }

        return $project->emailLinks()
            ->where('user_id', $user->id)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->get()
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
            'status' => $link->status->value,
            'last_verified_at' => $link->last_verified_at?->toIso8601String(),
        ];
    }

    private function requireAccount(User $user): UserImapAccount
    {
        $account = $user->imapAccount;

        abort_if($account === null, 422, Translations::get('settings.email.errors.not_configured'));

        return $account;
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
