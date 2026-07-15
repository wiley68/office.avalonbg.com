<?php

namespace App\Services;

use App\Enums\ProjectEmailLinkStatus;
use App\Models\Project;
use App\Models\ProjectEmailAttachment;
use App\Models\ProjectEmailLink;
use App\Models\User;
use App\Models\UserImapAccount;
use App\Support\EmailBodyEncoding;
use App\Support\EmailConversation;
use App\Support\Translations;
use Illuminate\Support\Str;

class ProjectEmailArchiveService
{
    public function __construct(
        private readonly ImapMailboxService $imapMailboxService,
        private readonly DocumentStorageService $documentStorageService,
        private readonly EmailConversation $emailConversation,
        private readonly EmailBodyEncoding $emailBodyEncoding,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    public function archiveMessages(User $user, Project $project, array $messages): void
    {
        $account = $this->requireAccount($user);

        foreach ($messages as $message) {
            $this->archiveMessage($user, $project, $account, $message);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function archiveMessage(
        User $user,
        Project $project,
        UserImapAccount $account,
        array $message,
    ): ProjectEmailLink {
        $link = $project->emailLinks()->updateOrCreate(
            [
                'folder' => (string) $message['folder'],
                'imap_uid' => (int) $message['imap_uid'],
            ],
            [
                'user_id' => $user->id,
                'uidvalidity' => (int) $message['uidvalidity'],
                'subject' => $message['subject'] ?? null,
                'from_name' => $message['from_name'] ?? null,
                'from_address' => $message['from_address'] ?? null,
                'sent_at' => $message['sent_at'] ?? null,
                'status' => ProjectEmailLinkStatus::Active,
                'last_verified_at' => now(),
                'conversation_key' => $this->emailConversation->conversationKey(
                    (string) ($message['subject'] ?? ''),
                ),
            ],
        );

        if (! $link->isArchived()) {
            $body = $this->imapMailboxService->fetchMessageBody(
                $account,
                $link->folder,
                $link->imap_uid,
            );

            $link->update([
                'body_text' => $this->normalizeStoredBody($body['text']),
                'body_html' => $this->normalizeStoredBody($body['html']),
                'archived_at' => now(),
                'status' => ProjectEmailLinkStatus::Active,
            ]);
        }

        $this->archiveAttachments($user, $project, $account, $link);

        return $link;
    }

    private function requireAccount(User $user): UserImapAccount
    {
        $account = $user->imapAccount;

        abort_if($account === null, 422, Translations::get('settings.email.errors.not_configured'));

        return $account;
    }

    private function archiveAttachments(
        User $user,
        Project $project,
        UserImapAccount $account,
        ProjectEmailLink $link,
    ): void {
        $attachments = $this->imapMailboxService->fetchMessageAttachments(
            $account,
            $link->folder,
            $link->imap_uid,
        );

        if ($attachments === []) {
            return;
        }

        $description = $this->buildAttachmentDescription($link);

        foreach ($attachments as $attachment) {
            $existing = ProjectEmailAttachment::query()
                ->where('project_email_link_id', $link->id)
                ->where('imap_part', $attachment['part'])
                ->first();

            if ($existing !== null) {
                $project->documents()->syncWithoutDetaching([$existing->document_id]);

                continue;
            }

            $part = $this->imapMailboxService->fetchAttachmentPart(
                $account,
                $link->folder,
                $link->imap_uid,
                $attachment['part'],
            );

            $document = $this->documentStorageService->storeFromContents(
                $user,
                $part['content'],
                $part['filename'],
                $part['mime_type'],
                $description,
            );

            $project->documents()->syncWithoutDetaching([$document->id]);

            ProjectEmailAttachment::query()->create([
                'project_email_link_id' => $link->id,
                'imap_part' => $attachment['part'],
                'document_id' => $document->id,
            ]);
        }
    }

    private function buildAttachmentDescription(ProjectEmailLink $link): string
    {
        $subject = trim((string) ($link->subject ?? ''));
        $rawBody = $link->body_text ?? strip_tags((string) ($link->body_html ?? ''));
        $excerpt = Str::of($rawBody)->squish()->limit(120)->toString();

        if ($subject === '') {
            return $excerpt;
        }

        if ($excerpt === '') {
            return $subject;
        }

        return $subject.' — '.$excerpt;
    }

    private function normalizeStoredBody(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return $body;
        }

        return $this->emailBodyEncoding->toUtf8($body);
    }
}
