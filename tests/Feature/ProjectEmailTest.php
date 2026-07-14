<?php

use App\Enums\ProjectEmailLinkStatus;
use App\Models\Project;
use App\Models\ProjectEmailLink;
use App\Models\User;
use App\Models\UserImapAccount;
use App\Services\ImapMailboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
});

function createOfficeUserWithImap(): array
{
    $user = User::factory()->create();
    $user->assignRole('user');

    $account = UserImapAccount::factory()->for($user)->create();

    return [$user, $account];
}

test('office user can link imap message to own project', function () {
    [$user] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();

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
        ->status->toBe(ProjectEmailLinkStatus::Active);
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
        ->assertJsonPath('data.links.0.subject', 'Updated subject')
        ->assertJsonPath('data.links.0.status', 'active');
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
        ->assertJsonPath('data.links.0.status', 'missing_on_server');

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

test('office user can remove email link from own project', function () {
    [$user] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();
    $link = ProjectEmailLink::factory()->for($project)->for($user)->create();

    actingAs($user);

    delete(route('projects.email.destroy', [$project, $link]))->assertRedirect();

    expect($project->fresh()->emailLinks)->toHaveCount(0);
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
    [$user] = createOfficeUserWithImap();

    $project = Project::factory()->for($user)->create();

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

    expect($project->fresh()->emailLinks)->toHaveCount(2);
});
