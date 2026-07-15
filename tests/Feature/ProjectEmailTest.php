<?php

use App\Enums\ProjectEmailLinkStatus;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectEmailAttachment;
use App\Models\ProjectEmailLink;
use App\Models\User;
use App\Models\UserImapAccount;
use App\Services\ImapMailboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\getJson;
use function Pest\Laravel\mock;
use function Pest\Laravel\post;
use function Pest\Laravel\withoutVite;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('user', 'web');
    Storage::fake('local');
});

function createOfficeUserWithImap(): array
{
    $user = User::factory()->create();
    $user->assignRole('user');

    $account = UserImapAccount::factory()->for($user)->create();

    return [$user, $account];
}

/**
 * @param  list<array{part: string, filename: string}>  $attachments
 */
function mockImapArchiveResponses(
    UserImapAccount $account,
    string $folder,
    int $uid,
    array $body = ['text' => 'Archived body text.', 'html' => null],
    array $attachments = [],
): MockInterface {
    /** @var MockInterface&ImapMailboxService $imapMailboxService */
    $imapMailboxService = mock(ImapMailboxService::class);

    $imapMailboxService
        ->shouldReceive('fetchMessageBody')
        ->with(
            Mockery::on(fn ($arg) => $arg->is($account)),
            $folder,
            $uid,
        )
        ->andReturn($body);

    $imapMailboxService
        ->shouldReceive('fetchMessageAttachments')
        ->with(
            Mockery::on(fn ($arg) => $arg->is($account)),
            $folder,
            $uid,
        )
        ->andReturn($attachments);

    foreach ($attachments as $attachment) {
        $imapMailboxService
            ->shouldReceive('fetchAttachmentPart')
            ->with(
                Mockery::on(fn ($arg) => $arg->is($account)),
                $folder,
                $uid,
                $attachment['part'],
            )
            ->andReturn([
                'filename' => $attachment['filename'],
                'content' => $attachment['content'] ?? '%PDF-1.4 archived',
                'mime_type' => $attachment['mime_type'] ?? 'application/pdf',
            ]);
    }

    return $imapMailboxService;
}

test('office user can link imap message to own project', function () {
    [$user, $account] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();

    mockImapArchiveResponses($account, 'INBOX', 42);

    actingAs($user);

    post(route('projects.email.store', $project), [
        'folder' => 'INBOX',
        'imap_uid' => 42,
        'uidvalidity' => 123456,
        'subject' => 'Project update',
        'from_name' => 'Alice',
        'from_address' => 'alice@example.com',
        'sent_at' => '2026-07-10T10:00:00Z',
    ])->assertRedirect();

    expect($project->fresh()->emailLinks)->toHaveCount(1)
        ->and($project->emailLinks->first())
        ->imap_uid->toBe(42)
        ->subject->toBe('Project update')
        ->status->toBe(ProjectEmailLinkStatus::Active)
        ->body_text->toBe('Archived body text.')
        ->archived_at->not->toBeNull();
});

test('office user can refresh linked emails for own project', function () {
    [$user, $account] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();

    ProjectEmailLink::factory()->for($project)->for($user)->create([
        'folder' => 'INBOX',
        'imap_uid' => 99,
        'uidvalidity' => 555,
        'subject' => 'Old subject',
    ]);

    /** @var MockInterface&ImapMailboxService $imapMailboxService */
    $imapMailboxService = mock(ImapMailboxService::class);

    $imapMailboxService
        ->shouldReceive('folderUidValidity')
        ->once()
        ->with(
            Mockery::on(fn ($arg) => $arg->is($account)),
            'INBOX',
        )
        ->andReturn(555);

    $imapMailboxService
        ->shouldReceive('fetchMessageSummary')
        ->once()
        ->with(
            Mockery::on(fn ($arg) => $arg->is($account)),
            'INBOX',
            99,
        )
        ->andReturn([
            'uidvalidity' => 555,
            'subject' => 'Updated subject',
            'from_name' => 'Bob',
            'from_address' => 'bob@example.com',
            'sent_at' => '2026-07-11T12:00:00Z',
        ]);

    actingAs($user)
        ->getJson(route('internal.projects.email.index', $project))
        ->assertOk()
        ->assertJsonPath('data.configured', true)
        ->assertJsonPath('data.threads.0.subject', 'Updated subject')
        ->assertJsonPath('data.threads.0.messages.0.status', 'active');
});

test('linked project emails are grouped into conversation threads', function () {
    [$user, $account] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();

    $older = ProjectEmailLink::factory()->for($project)->for($user)->create([
        'folder' => 'INBOX.Archives.Current',
        'imap_uid' => 1201,
        'uidvalidity' => 555,
        'subject' => 'BR Avalon',
        'sent_at' => '2026-07-10T10:00:00Z',
    ]);

    $newer = ProjectEmailLink::factory()->for($project)->for($user)->create([
        'folder' => 'INBOX.Archives.Current',
        'imap_uid' => 1202,
        'uidvalidity' => 555,
        'subject' => 'Re: BR Avalon',
        'sent_at' => '2026-07-11T12:00:00Z',
    ]);

    /** @var MockInterface&ImapMailboxService $imapMailboxService */
    $imapMailboxService = mock(ImapMailboxService::class);

    $imapMailboxService
        ->shouldReceive('folderUidValidity')
        ->once()
        ->andReturn(555);

    $imapMailboxService
        ->shouldReceive('fetchMessageSummary')
        ->twice()
        ->andReturnUsing(function ($account, string $folder, int $uid) use ($older, $newer) {
            return match ($uid) {
                $older->imap_uid => [
                    'uidvalidity' => 555,
                    'subject' => 'BR Avalon',
                    'from_name' => 'Alice',
                    'from_address' => 'alice@example.com',
                    'sent_at' => '2026-07-10T10:00:00Z',
                ],
                $newer->imap_uid => [
                    'uidvalidity' => 555,
                    'subject' => 'Re: BR Avalon',
                    'from_name' => 'Bob',
                    'from_address' => 'bob@example.com',
                    'sent_at' => '2026-07-11T12:00:00Z',
                ],
                default => null,
            };
        });

    actingAs($user)
        ->getJson(route('internal.projects.email.index', $project))
        ->assertOk()
        ->assertJsonPath('data.threads', fn ($threads) => count($threads) === 1)
        ->assertJsonPath('data.threads.0.message_count', 2)
        ->assertJsonPath('data.threads.0.messages.0.id', $older->id)
        ->assertJsonPath('data.threads.0.messages.1.id', $newer->id);
});

test('refresh marks link as missing when message is not on server', function () {
    [$user, $account] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();

    $link = ProjectEmailLink::factory()->for($project)->for($user)->create([
        'folder' => 'INBOX',
        'imap_uid' => 77,
        'uidvalidity' => 444,
    ]);

    /** @var MockInterface&ImapMailboxService $imapMailboxService */
    $imapMailboxService = mock(ImapMailboxService::class);

    $imapMailboxService
        ->shouldReceive('folderUidValidity')
        ->once()
        ->andReturn(444);

    $imapMailboxService
        ->shouldReceive('fetchMessageSummary')
        ->once()
        ->andReturn(null);

    actingAs($user)
        ->getJson(route('internal.projects.email.index', $project))
        ->assertOk()
        ->assertJsonPath('data.threads.0.messages.0.status', 'missing_on_server');

    expect($link->fresh()->status)->toBe(ProjectEmailLinkStatus::MissingOnServer);
});

test('office user can browse imap messages', function () {
    [$user, $account] = createOfficeUserWithImap();

    mock(ImapMailboxService::class)
        ->shouldReceive('browseRecentMessages')
        ->once()
        ->with(
            Mockery::on(fn ($arg) => $arg->is($account)),
            'INBOX',
            50,
            '',
        )
        ->andReturn([
            [
                'uid' => 10,
                'uidvalidity' => 999,
                'subject' => 'Hello',
                'from_name' => 'Alice',
                'from_address' => 'alice@example.com',
                'sent_at' => '2026-07-10T10:00:00Z',
            ],
        ]);

    actingAs($user)
        ->getJson(route('internal.imap.messages.index'))
        ->assertOk()
        ->assertJsonPath('data.0.subject', 'Hello')
        ->assertJsonPath('meta.folder', 'INBOX');
});

test('office user can browse imap threads', function () {
    [$user, $account] = createOfficeUserWithImap();

    /** @var MockInterface&ImapMailboxService $imapMailboxService */
    $imapMailboxService = mock(ImapMailboxService::class);

    $imapMailboxService
        ->shouldReceive('browseMessageThreads')
        ->once()
        ->with(
            Mockery::on(fn ($arg) => $arg->is($account)),
            'INBOX.Archives.Current',
            100,
            '',
        )
        ->andReturn([
            [
                'id' => 'imap-10',
                'grouping' => 'imap_thread',
                'message_count' => 2,
                'subject' => 'Project kickoff',
                'latest_sent_at' => '2026-07-11T12:00:00Z',
                'messages' => [
                    [
                        'uid' => 10,
                        'uidvalidity' => 999,
                        'subject' => 'Project kickoff',
                        'from_name' => 'Alice',
                        'from_address' => 'alice@example.com',
                        'sent_at' => '2026-07-10T10:00:00Z',
                    ],
                    [
                        'uid' => 11,
                        'uidvalidity' => 999,
                        'subject' => 'Re: Project kickoff',
                        'from_name' => 'Bob',
                        'from_address' => 'bob@example.com',
                        'sent_at' => '2026-07-11T12:00:00Z',
                    ],
                ],
            ],
        ]);

    actingAs($user)
        ->getJson(route('internal.imap.threads.index', ['folder' => 'INBOX.Archives.Current']))
        ->assertOk()
        ->assertJsonPath('data.0.id', 'imap-10')
        ->assertJsonPath('data.0.message_count', 2)
        ->assertJsonPath('data.0.messages.1.subject', 'Re: Project kickoff');
});

test('office user can fetch imap attachment names for messages', function () {
    [$user, $account] = createOfficeUserWithImap();

    /** @var MockInterface&ImapMailboxService $imapMailboxService */
    $imapMailboxService = mock(ImapMailboxService::class);

    $imapMailboxService
        ->shouldReceive('fetchAttachmentNamesForUids')
        ->once()
        ->with(
            Mockery::on(fn ($arg) => $arg->is($account)),
            'INBOX.Archives.Current',
            [1205, 1206],
        )
        ->andReturn([
            1205 => [],
            1206 => ['specification.pdf', 'diagram.png'],
        ]);

    actingAs($user)
        ->getJson(route('internal.imap.attachments.index', [
            'folder' => 'INBOX.Archives.Current',
            'uids' => '1205,1206',
        ]))
        ->assertOk()
        ->assertJsonPath('data.1206', ['specification.pdf', 'diagram.png'])
        ->assertJsonPath('data.1205', []);
});

test('office user can fetch imap message body excerpt source', function () {
    [$user, $account] = createOfficeUserWithImap();

    /** @var MockInterface&ImapMailboxService $imapMailboxService */
    $imapMailboxService = mock(ImapMailboxService::class);

    $imapMailboxService
        ->shouldReceive('fetchMessageBody')
        ->once()
        ->with(
            Mockery::on(fn ($arg) => $arg->is($account)),
            'INBOX.Archives.Current',
            1206,
        )
        ->andReturn([
            'text' => 'Hello from the client. This is the message body preview.',
            'html' => null,
        ]);

    actingAs($user)
        ->getJson(route('internal.imap.body.index', [
            'folder' => 'INBOX.Archives.Current',
            'uid' => 1206,
        ]))
        ->assertOk()
        ->assertJsonPath('data.text', 'Hello from the client. This is the message body preview.');
});

test('office user can fetch linked email body', function () {
    [$user, $account] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();

    $link = ProjectEmailLink::factory()->for($project)->for($user)->create([
        'status' => ProjectEmailLinkStatus::Active,
    ]);

    mock(ImapMailboxService::class)
        ->shouldReceive('fetchMessageBody')
        ->once()
        ->with(
            Mockery::on(fn ($arg) => $arg->is($account)),
            $link->folder,
            $link->imap_uid,
        )
        ->andReturn([
            'text' => 'Plain text body',
            'html' => null,
        ]);

    actingAs($user)
        ->getJson(route('internal.projects.email.body', [$project, $link]))
        ->assertOk()
        ->assertJsonPath('data.text', 'Plain text body');
});

test('office user can list linked email attachments', function () {
    [$user, $account] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();

    $link = ProjectEmailLink::factory()->for($project)->for($user)->create([
        'status' => ProjectEmailLinkStatus::Active,
    ]);

    mock(ImapMailboxService::class)
        ->shouldReceive('fetchMessageAttachments')
        ->once()
        ->with(
            Mockery::on(fn ($arg) => $arg->is($account)),
            $link->folder,
            $link->imap_uid,
        )
        ->andReturn([
            ['part' => '2', 'filename' => 'specification.pdf'],
            ['part' => '3', 'filename' => 'diagram.png'],
        ]);

    actingAs($user)
        ->getJson(route('internal.projects.email.attachments.index', [$project, $link]))
        ->assertOk()
        ->assertJsonPath('data.0.filename', 'specification.pdf')
        ->assertJsonPath('data.1.part', '3');
});

test('office user can download linked email attachment', function () {
    [$user, $account] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();

    $link = ProjectEmailLink::factory()->for($project)->for($user)->create([
        'status' => ProjectEmailLinkStatus::Active,
    ]);

    mock(ImapMailboxService::class)
        ->shouldReceive('fetchAttachmentPart')
        ->twice()
        ->with(
            Mockery::on(fn ($arg) => $arg->is($account)),
            $link->folder,
            $link->imap_uid,
            '2',
        )
        ->andReturn([
            'filename' => 'specification.pdf',
            'content' => '%PDF-1.4',
            'mime_type' => 'application/pdf',
        ]);

    actingAs($user)
        ->get(route('internal.projects.email.attachments.download', [$project, $link, '2']))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="specification.pdf"; filename*=UTF-8\'\'specification.pdf')
        ->assertSee('%PDF-1.4');

    actingAs($user)
        ->get(route('internal.projects.email.attachments.download', [
            'project' => $project,
            'emailLink' => $link,
            'part' => '2',
            'download' => 1,
        ]))
        ->assertOk()
        ->assertHeader(
            'content-disposition',
            'attachment; filename="specification.pdf"; filename*=UTF-8\'\'specification.pdf',
        );
});

test('office user can remove email link from own project', function () {
    [$user] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();
    $link = ProjectEmailLink::factory()->for($project)->for($user)->create();

    actingAs($user);

    delete(route('projects.email.destroy', [$project, $link]))->assertRedirect();

    expect($project->fresh()->emailLinks)->toHaveCount(0);
});

test('office user can remove entire email thread from own project', function () {
    [$user] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();

    $older = ProjectEmailLink::factory()->for($project)->for($user)->create([
        'subject' => 'BR Avalon',
        'sent_at' => '2026-07-10T10:00:00Z',
    ]);

    $newer = ProjectEmailLink::factory()->for($project)->for($user)->create([
        'subject' => 'Re: BR Avalon',
        'sent_at' => '2026-07-11T12:00:00Z',
    ]);

    actingAs($user);

    delete(route('projects.email.thread.destroy', $project), [
        'link_ids' => [$older->id, $newer->id],
    ])->assertRedirect();

    expect($project->fresh()->emailLinks)->toHaveCount(0);
});

test('office user cannot remove email thread with foreign link ids', function () {
    [$user] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->for($user)->create();

    $ownLink = ProjectEmailLink::factory()->for($project)->for($user)->create();
    $foreignLink = ProjectEmailLink::factory()->for($otherProject)->for($user)->create();

    actingAs($user);

    delete(route('projects.email.thread.destroy', $project), [
        'link_ids' => [$ownLink->id, $foreignLink->id],
    ])->assertSessionHasErrors('link_ids');

    expect($project->fresh()->emailLinks)->toHaveCount(1)
        ->and($otherProject->fresh()->emailLinks)->toHaveCount(1);
});

test('office user cannot manage email links for another users project', function () {
    [$owner] = createOfficeUserWithImap();

    $other = User::factory()->create();
    $other->assignRole('user');
    UserImapAccount::factory()->for($other)->create();

    $project = Project::factory()->for($owner)->create();

    actingAs($other);

    post(route('projects.email.store', $project), [
        'folder' => 'INBOX',
        'imap_uid' => 1,
        'uidvalidity' => 1,
    ])->assertNotFound();

    getJson(route('internal.projects.email.index', $project))
        ->assertNotFound();
});

test('office user can open email link page for own project', function () {
    withoutVite();

    [$user] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();

    actingAs($user)
        ->get(route('projects.email.link', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/email/Link')
            ->where('project.id', $project->id)
            ->where('defaultFolder', 'INBOX'));
});

test('office user can browse imap folders', function () {
    [$user, $account] = createOfficeUserWithImap();

    /** @var MockInterface&ImapMailboxService $imapMailboxService */
    $imapMailboxService = mock(ImapMailboxService::class);

    $imapMailboxService
        ->shouldReceive('listFolderTree')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg->is($account)))
        ->andReturn([
            [
                'name' => 'INBOX',
                'path' => 'INBOX',
                'children' => [
                    [
                        'name' => 'Projects',
                        'path' => 'INBOX.Projects',
                        'children' => [],
                    ],
                ],
            ],
        ]);

    actingAs($user)
        ->getJson(route('internal.imap.folders.index'))
        ->assertOk()
        ->assertJsonPath('data.0.path', 'INBOX')
        ->assertJsonPath('data.0.children.0.path', 'INBOX.Projects');
});

test('office user can batch link messages to own project', function () {
    [$user, $account] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();

    /** @var MockInterface&ImapMailboxService $imapMailboxService */
    $imapMailboxService = mock(ImapMailboxService::class);

    foreach ([10, 11] as $uid) {
        $imapMailboxService
            ->shouldReceive('fetchMessageBody')
            ->with(
                Mockery::on(fn ($arg) => $arg->is($account)),
                'INBOX.Projects',
                $uid,
            )
            ->once()
            ->andReturn(['text' => "Body {$uid}", 'html' => null]);

        $imapMailboxService
            ->shouldReceive('fetchMessageAttachments')
            ->with(
                Mockery::on(fn ($arg) => $arg->is($account)),
                'INBOX.Projects',
                $uid,
            )
            ->once()
            ->andReturn([]);
    }

    actingAs($user);

    post(route('projects.email.batch.store', $project), [
        'messages' => [
            [
                'folder' => 'INBOX.Projects',
                'imap_uid' => 10,
                'uidvalidity' => 999,
                'subject' => 'First',
                'from_name' => 'Alice',
                'from_address' => 'alice@example.com',
                'sent_at' => '2026-07-10T10:00:00Z',
            ],
            [
                'folder' => 'INBOX.Projects',
                'imap_uid' => 11,
                'uidvalidity' => 999,
                'subject' => 'Second',
                'from_name' => 'Bob',
                'from_address' => 'bob@example.com',
                'sent_at' => '2026-07-11T12:00:00Z',
            ],
        ],
    ])->assertRedirect(route('projects.show', [
        'project' => $project,
        'tab' => 'email',
    ]));

    expect($project->fresh()->emailLinks)->toHaveCount(2)
        ->and($project->fresh()->emailLinks->every(fn ($link) => $link->archived_at !== null))->toBeTrue();
});

test('batch link archives attachments into documents and attaches them to project', function () {
    [$user, $account] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();

    mockImapArchiveResponses(
        $account,
        'INBOX.Projects',
        20,
        ['text' => 'Please review the attached specification.', 'html' => null],
        [['part' => '2', 'filename' => 'specification.pdf']],
    );

    actingAs($user);

    post(route('projects.email.batch.store', $project), [
        'messages' => [
            [
                'folder' => 'INBOX.Projects',
                'imap_uid' => 20,
                'uidvalidity' => 999,
                'subject' => 'Specification',
                'from_name' => 'Alice',
                'from_address' => 'alice@example.com',
                'sent_at' => '2026-07-10T10:00:00Z',
            ],
        ],
    ])->assertRedirect();

    $link = $project->fresh()->emailLinks->first();

    expect($link->attachments)->toHaveCount(1)
        ->and($link->attachments->first()->document->original_name)->toBe('specification.pdf')
        ->and($project->fresh()->documents)->toHaveCount(1)
        ->and($project->documents->first()->description)->toContain('Specification');
});

test('batch link preserves binary attachment bytes when archiving documents', function () {
    [$user, $account] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();

    $binaryContent = "%PDF-1.4\n\x00\x01\x02".random_bytes(120);

    mockImapArchiveResponses(
        $account,
        'INBOX.Projects',
        30,
        ['text' => 'Binary attachment test.', 'html' => null],
        [[
            'part' => '2',
            'filename' => 'report.pdf',
            'content' => $binaryContent,
            'mime_type' => 'application/pdf',
        ]],
    );

    actingAs($user);

    post(route('projects.email.batch.store', $project), [
        'messages' => [
            [
                'folder' => 'INBOX.Projects',
                'imap_uid' => 30,
                'uidvalidity' => 999,
                'subject' => 'Report',
                'sent_at' => '2026-07-10T10:00:00Z',
            ],
        ],
    ])->assertRedirect();

    $document = $project->fresh()->emailLinks->first()->attachments->first()->document;

    expect($document->size_bytes)->toBe(strlen($binaryContent))
        ->and(Storage::disk('local')->get($document->storage_path))
        ->toBe($binaryContent);
});

test('re-linking the same imap message does not duplicate archived content', function () {
    [$user, $account] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();

    mockImapArchiveResponses(
        $account,
        'INBOX',
        42,
        ['text' => 'Original archived body.', 'html' => null],
        [['part' => '2', 'filename' => 'notes.pdf']],
    );

    actingAs($user);

    post(route('projects.email.store', $project), [
        'folder' => 'INBOX',
        'imap_uid' => 42,
        'uidvalidity' => 123456,
        'subject' => 'Project update',
        'sent_at' => '2026-07-10T10:00:00Z',
    ])->assertRedirect();

    post(route('projects.email.store', $project), [
        'folder' => 'INBOX',
        'imap_uid' => 42,
        'uidvalidity' => 123456,
        'subject' => 'Project update',
        'sent_at' => '2026-07-10T10:00:00Z',
    ])->assertRedirect();

    expect($project->fresh()->emailLinks)->toHaveCount(1)
        ->and($project->emailLinks->first()->attachments)->toHaveCount(1)
        ->and($project->documents)->toHaveCount(1);
});

test('archived email body and attachments are served from database without imap', function () {
    [$user] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();

    $link = ProjectEmailLink::factory()->for($project)->for($user)->archived()->create([
        'subject' => 'Archived subject',
        'body_text' => 'Stored body for later reference.',
    ]);

    $document = Document::factory()->for($user)->create([
        'original_name' => 'invoice.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $project->documents()->attach($document);

    ProjectEmailAttachment::query()->create([
        'project_email_link_id' => $link->id,
        'imap_part' => '2',
        'document_id' => $document->id,
    ]);

    $user->imapAccount()->delete();

    actingAs($user)
        ->getJson(route('internal.projects.email.index', $project))
        ->assertOk()
        ->assertJsonPath('data.configured', true)
        ->assertJsonPath('data.threads.0.messages.0.is_archived', true);

    actingAs($user)
        ->getJson(route('internal.projects.email.body', [$project, $link]))
        ->assertOk()
        ->assertJsonPath('data.text', 'Stored body for later reference.');

    actingAs($user)
        ->getJson(route('internal.projects.email.attachments.index', [$project, $link]))
        ->assertOk()
        ->assertJsonPath('data.0.filename', 'invoice.pdf');
});
