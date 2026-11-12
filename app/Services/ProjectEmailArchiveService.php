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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

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
        $this->authorizeMailbox($user, $project);

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
        $storedPaths = [];

        try {
            return $project->getConnection()->transaction(function () use ($user, $project, $account, $message, &$storedPaths): ProjectEmailLink {
                $project = Project::query()->lockForUpdate()->findOrFail($project->id);
                $user = User::query()->lockForUpdate()->findOrFail($user->id);
                $this->authorizeMailbox($user, $project);
                $currentAccount = $user->imapAccount()->lockForUpdate()->first();
                abort_unless($currentAccount !== null && $account->user_id === $user->id
                    && $currentAccount->is($account) && $currentAccount->mailboxIdentity() === $account->mailboxIdentity(), 403);
                $account = $currentAccount;

                $link = $project->emailLinks()
                    ->where('source_mailbox', $account->mailboxIdentity())
                    ->where('folder', (string) $message['folder'])
                    ->where('uidvalidity', (int) $message['uidvalidity'])
                    ->where('imap_uid', (int) $message['imap_uid'])
                    ->first();

                if ($link?->isArchived()) {
                    return $link;
                }

                abort_if($link !== null && $link->user_id !== $user->id, 403);
                $this->verifyUidValidity($account, (string) $message['folder'], (int) $message['uidvalidity']);

                if ($link === null) {
                    $link = $project->emailLinks()->make([
                        'user_id' => $user->id,
                        'folder' => (string) $message['folder'],
                        'imap_uid' => (int) $message['imap_uid'],
                        'uidvalidity' => (int) $message['uidvalidity'],
                        'subject' => $message['subject'] ?? null,
                        'from_name' => $message['from_name'] ?? null,
                        'from_address' => $message['from_address'] ?? null,
                        'sent_at' => $message['sent_at'] ?? null,
                        'status' => ProjectEmailLinkStatus::Active,
                        'conversation_key' => $this->emailConversation->conversationKey((string) ($message['subject'] ?? '')),
                    ]);
                    $link->forceFill(['source_mailbox' => $account->mailboxIdentity()])->save();
                }

                $body = $this->imapMailboxService->fetchMessageBody($account, $link->folder, $link->imap_uid);
                $link->fill([
                    'body_text' => $this->normalizeStoredBody($body['text']),
                    'body_html' => $this->normalizeStoredBody($body['html']),
                ]);
                $this->archiveAttachments($user, $project, $account, $link, $storedPaths);
                $this->verifyUidValidity($account, $link->folder, $link->uidvalidity);
                $link->fill([
                    'archived_at' => now(),
                    'last_verified_at' => now(),
                    'status' => ProjectEmailLinkStatus::Active,
                ])->save();

                return $link;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            if ($exception instanceof HttpExceptionInterface || $exception instanceof AuthorizationException) {
                throw $exception;
            }

            abort(422, Translations::get('projects.email.errors.archive_failed'));
        }
    }

    private function verifyUidValidity(UserImapAccount $account, string $folder, int $expected): void
    {
        $actual = $this->imapMailboxService->folderUidValidity($account, $folder);
        abort_unless($actual > 0 && $actual === $expected, 422, Translations::get('projects.email.errors.missing_on_server'));
    }

    private function authorizeMailbox(User $user, Project $project): void
    {
        abort_unless($user->isActive(), 403);
        Gate::forUser($user)->authorize('update', $project);
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
        array &$storedPaths,
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
                abort_unless(Storage::disk('local')->exists($existing->document->storage_path), 422, Translations::get('projects.email.errors.archive_failed'));
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

            $storedPaths[] = $document->storage_path;
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
