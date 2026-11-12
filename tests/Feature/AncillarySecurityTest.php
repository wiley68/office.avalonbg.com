<?php

use App\Enums\ProjectEmailLinkStatus;
use App\Models\Document;
use App\Models\Group;
use App\Models\ProfitEntry;
use App\Models\ProfitType;
use App\Models\Project;
use App\Models\ProjectEmailAttachment;
use App\Models\ProjectEmailLink;
use App\Models\User;
use App\Models\UserImapAccount;
use App\Services\DocumentStorageService;
use App\Services\ImapMailboxService;
use App\Services\ProfitsExportDataBuilder;
use App\Services\ProfitsXlsxExportService;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (['user', 'admin', 'profiler'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    Storage::fake('local');
    $this->creator = User::factory()->officeUser()->create();
    $this->peer = User::factory()->officeUser($this->creator->group)->create();
    $this->outsider = User::factory()->officeUser()->create();
    $this->project = Project::factory()->for($this->creator)->create();
    $this->account = UserImapAccount::factory()->for($this->creator)->create();
    $this->message = ['folder' => 'INBOX', 'imap_uid' => 42, 'uidvalidity' => 100, 'subject' => 'Original'];
});

function ancillaryImap(int $validity = 100, ?int $nextValidity = null): MockInterface
{
    $imap = Mockery::mock(ImapMailboxService::class);
    $imap->shouldReceive('folderUidValidity')->andReturn($validity, $nextValidity ?? $validity);
    $imap->shouldReceive('fetchMessageBody')->andReturn(['text' => 'Archived secret', 'html' => null])->byDefault();
    $imap->shouldReceive('fetchMessageAttachments')->andReturn([])->byDefault();
    app()->instance(ImapMailboxService::class, $imap);

    return $imap;
}

test('distinct mailbox and UIDVALIDITY identities never overwrite an archive and creator', function (): void {
    ancillaryImap();
    $this->actingAs($this->creator)->postJson(route('projects.email.store', $this->project), $this->message)->assertRedirect();
    $original = $this->project->emailLinks()->firstOrFail();
    $peerAccount = UserImapAccount::factory()->for($this->peer)->create();
    $this->actingAs($this->peer)->postJson(route('projects.email.store', $this->project), $this->message)->assertRedirect();
    expect($this->project->emailLinks()->count())->toBe(2)
        ->and($original->fresh()->user_id)->toBe($this->creator->id);

    $peerAccount->update(['username' => $this->account->username, 'host' => $this->account->host, 'password' => 'changed-password']);
    $this->peer->unsetRelation('imapAccount');
    app()->instance(ImapMailboxService::class, Mockery::mock(ImapMailboxService::class));
    $this->postJson(route('projects.email.store', $this->project), [...$this->message, 'subject' => 'Must not overwrite'])->assertRedirect();
    expect($this->project->emailLinks()->count())->toBe(2)
        ->and($original->fresh()->subject)->toBe('Original')
        ->and($original->fresh()->body_text)->toBe('Archived secret')
        ->and($original->fresh()->user_id)->toBe($this->creator->id);

    ancillaryImap(101);
    $this->postJson(route('projects.email.store', $this->project), [...$this->message, 'uidvalidity' => 101])->assertRedirect();
    expect($this->project->emailLinks()->count())->toBe(3);
    $this->getJson(route('internal.projects.email.body', [$this->project, $original]))->assertOk()->assertJsonPath('data.text', 'Archived secret');
    $this->actingAs($this->outsider)->getJson(route('internal.projects.email.body', [$this->project, $original]))->assertNotFound();
});

test('source changes unknown legacy sources and foreign live mail fail without mailbox reads', function (): void {
    $link = ProjectEmailLink::factory()->for($this->project)->create(['uidvalidity' => 100]);
    $imap = Mockery::mock(ImapMailboxService::class);
    $imap->shouldNotReceive('fetchMessageBody', 'fetchMessageAttachments', 'fetchAttachmentPart', 'folderUidValidity');
    app()->instance(ImapMailboxService::class, $imap);
    UserImapAccount::factory()->for($this->peer)->create();
    $this->actingAs($this->peer)->getJson(route('internal.projects.email.body', [$this->project, $link]))->assertForbidden();
    $this->getJson(route('internal.projects.email.index', $this->project))->assertOk()->assertJsonPath('data.threads.0.messages.0.can_read', false);
    $this->account->update(['username' => 'different-source@example.test']);
    $this->actingAs($this->creator)->getJson(route('internal.projects.email.body', [$this->project, $link]))->assertForbidden();
    $link->forceFill(['source_mailbox' => null])->save();
    $this->getJson(route('internal.projects.email.attachments.index', [$this->project, $link]))->assertForbidden();
    $this->getJson(route('internal.projects.email.attachments.download', [$this->project, $link, '2']))->assertForbidden();
});

test('UIDVALIDITY mismatch and unavailable validity prevent any UID body or attachment read', function (int $current): void {
    $link = ProjectEmailLink::factory()->for($this->project)->create(['uidvalidity' => 100]);
    $imap = Mockery::mock(ImapMailboxService::class);
    $imap->shouldReceive('folderUidValidity')->andReturn($current);
    $imap->shouldNotReceive('fetchMessageBody', 'fetchMessageAttachments', 'fetchAttachmentPart', 'fetchMessageSummary');
    app()->instance(ImapMailboxService::class, $imap);
    $this->actingAs($this->creator);
    foreach (['internal.projects.email.body', 'internal.projects.email.attachments.index'] as $route) {
        $response = $this->getJson(route($route, [$this->project, $link]));
        $current === 0 ? $response->assertUnprocessable() : $response->assertNotFound();
    }
    $response = $this->getJson(route('internal.projects.email.attachments.download', [$this->project, $link, '2']));
    $current === 0 ? $response->assertUnprocessable() : $response->assertNotFound();
    $this->postJson(route('projects.email.store', $this->project), $this->message)->assertUnprocessable();
    expect($link->fresh()->isArchived())->toBeFalse()->and($link->fresh()->uidvalidity)->toBe(100);
})->with([0, 101]);

test('attachment failure rolls back archive metadata documents pivots and only new files then permits retry', function (): void {
    $keep = app(DocumentStorageService::class)->storeFromContents($this->creator, 'keep', 'keep.txt', 'text/plain');
    $imap = Mockery::mock(ImapMailboxService::class);
    $imap->shouldReceive('folderUidValidity')->andReturn(100);
    $imap->shouldReceive('fetchMessageBody')->andReturn(['text' => 'Partial body', 'html' => null]);
    $imap->shouldReceive('fetchMessageAttachments')->andReturn([['part' => '2'], ['part' => '3']]);
    $imap->shouldReceive('fetchAttachmentPart')->withArgs(fn ($account, $folder, $uid, $part): bool => $part === '2')->andReturn(['content' => 'first', 'filename' => 'first.txt', 'mime_type' => 'text/plain']);
    $imap->shouldReceive('fetchAttachmentPart')->withArgs(fn ($account, $folder, $uid, $part): bool => $part === '3')->andThrow(new RuntimeException('sensitive IMAP error'));
    app()->instance(ImapMailboxService::class, $imap);
    $this->actingAs($this->creator)->postJson(route('projects.email.store', $this->project), $this->message)->assertUnprocessable()->assertDontSee('sensitive IMAP error');
    expect($this->project->emailLinks()->count())->toBe(0)->and(ProjectEmailAttachment::query()->count())->toBe(0)
        ->and($this->project->documents()->count())->toBe(0)->and(Document::query()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toBe([$keep->storage_path]);
    ancillaryImap();
    $this->postJson(route('projects.email.store', $this->project), $this->message)->assertRedirect();
    expect($this->project->emailLinks()->firstOrFail()->isArchived())->toBeTrue();
});

test('UIDVALIDITY change during archival rolls back instead of publishing mixed message data', function (): void {
    ancillaryImap(100, 101);
    $this->actingAs($this->creator)->postJson(route('projects.email.store', $this->project), $this->message)->assertUnprocessable();
    expect($this->project->emailLinks()->count())->toBe(0);
});

test('mailbox passwords and source identifiers are absent from shared serialization', function (): void {
    $link = ProjectEmailLink::factory()->for($this->project)->archived()->create();
    expect($this->account->toArray())->not->toHaveKey('password')
        ->and($link->toArray())->not->toHaveKey('source_mailbox');
    $this->actingAs($this->peer)->getJson(route('internal.projects.email.index', $this->project))->assertOk()
        ->assertDontSee($this->account->password)->assertDontSee($this->account->username)->assertDontSee($this->account->mailboxIdentity());
});

test('overlapping HTTP XLSX exports in the same second keep private paths contents and cleanup independent', function (): void {
    $this->freezeTime();
    $responses = [];
    $paths = [];
    try {
        foreach ([$this->creator, $this->outsider] as $index => $user) {
            $type = ProfitType::factory()->for($user->group)->income()->create();
            ProfitEntry::factory()->for($user)->for($type, 'profitType')->create(['date' => '2026-08-10', 'description' => 'GROUP-'.($index + 1), 'amount' => ($index + 1) * 123]);
        }
        foreach ([$this->creator, $this->outsider] as $user) {
            $responses[] = $this->actingAs($user)->postJson(route('profits.export'), ['date_from' => '2026-08-01', 'date_to' => '2026-08-31', 'format' => 'xlsx'])->assertOk();
        }
        foreach ($responses as $index => $response) {
            $path = $response->baseResponse->getFile()->getPathname();
            $paths[] = $path;
            expect($path)->toStartWith(storage_path('app/private/exports/'))
                ->and(fileperms($path) & 0777)->toBe(0600)
                ->and($response->headers->get('cache-control'))->toContain('private')->toContain('no-store');
            $workbook = IOFactory::load($path);
            $cells = collect($workbook->getActiveSheet()->toArray())->flatten()->all();
            expect($cells)->toContain('GROUP-'.($index + 1))->not->toContain('GROUP-'.(2 - $index));
            $workbook->disconnectWorksheets();
        }
        expect($paths[0])->not->toBe($paths[1])
            ->and($responses[0]->headers->get('content-disposition'))->toBe($responses[1]->headers->get('content-disposition'));
        ob_start();
        try {
            $responses[0]->baseResponse->sendContent();
        } finally {
            ob_end_clean();
        }
        expect(file_exists($paths[0]))->toBeFalse()->and(file_exists($paths[1]))->toBeTrue();
        ob_start();
        try {
            $responses[1]->baseResponse->sendContent();
        } finally {
            ob_end_clean();
        }
        expect(file_exists($paths[1]))->toBeFalse();
    } finally {
        foreach ($paths as $path) {
            @unlink($path);
        }
    }
});

test('failed XLSX generation removes its own partial file', function (): void {
    $service = new class(app(ProfitsExportDataBuilder::class)) extends ProfitsXlsxExportService
    {
        public ?string $partialPath = null;

        public function writeToFile(User $user, string $dateFrom, string $dateTo, string $absolutePath): int
        {
            $this->partialPath = $absolutePath;
            file_put_contents($absolutePath, 'sensitive partial workbook');
            throw new RuntimeException('generation failed');
        }
    };
    expect(fn () => $service->download($this->creator, '2026-08-01', '2026-08-31', 'profits.xlsx'))->toThrow(RuntimeException::class)
        ->and(file_exists($service->partialPath))->toBeFalse();
});

test('hostile SVG is downloadable with sandbox and nosniff through documents and archived attachments', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="fetch(`/internal-api/accesses`)"><script>alert(document.domain)</script></svg>';
    $this->actingAs($this->creator)->post(route('documents.store'), ['file' => UploadedFile::fake()->createWithContent('hostile.SVG', $svg)])->assertRedirect()->assertSessionHasNoErrors();
    $document = Document::query()->firstOrFail();
    $link = ProjectEmailLink::factory()->for($this->project)->archived()->create();
    $attachment = ProjectEmailAttachment::factory()->for($link, 'emailLink')->for($document)->create();
    $this->actingAs($this->peer);
    foreach ([route('documents.download', $document), route('internal.projects.email.attachments.download', [$this->project, $link, $attachment->imap_part])] as $url) {
        $response = $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'");
        expect($response->headers->get('content-disposition'))->toStartWith('attachment;')
            ->and($response->streamedContent())->toBe($svg);
        $this->actingAs($this->outsider)->getJson($url)->assertNotFound();
        $this->actingAs($this->peer);
    }
    $document->update(['mime_type' => 'image/png']);
    $this->get(route('documents.download', $document))->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'");
});

test('live email SVG downloads use the same restrictive headers', function (): void {
    $link = ProjectEmailLink::factory()->for($this->project)->create(['uidvalidity' => 100]);
    $imap = ancillaryImap();
    $imap->shouldReceive('fetchAttachmentPart')->andReturn(['content' => '<svg onload="alert(1)"/>', 'filename' => 'image.svg', 'mime_type' => 'image/svg+xml']);
    $this->actingAs($this->creator)->get(route('internal.projects.email.attachments.download', [$this->project, $link, '2']))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'");
});

test('a stale page context refuses old forms even after reauthentication in another group', function (): void {
    $context = $this->creator->authorizationContext();
    $this->actingAs($this->creator)->withHeader('X-Office-Context', $context)->postJson(route('projects.store'), ['name' => 'Valid same-group form', 'status' => 'active'])->assertRedirect();
    $this->creator->forceFill(['group_id' => $this->outsider->group_id])->save();
    $this->actingAs($this->creator->fresh())->postJson(route('projects.store'), ['name' => 'Stale form'])->assertStatus(419);
    $this->getJson(route('internal.projects.index'))->assertStatus(419);
    expect(Project::query()->where('name', 'Stale form')->exists())->toBeFalse();
    $this->withHeader('X-Office-Context', $this->creator->fresh()->authorizationContext())->postJson(route('projects.store'), ['name' => 'Fresh form', 'status' => 'active'])->assertRedirect();
    expect(Project::query()->where('name', 'Fresh form')->firstOrFail()->group_id)->toBe($this->outsider->group_id);
});

test('role change and guest session refuse the old page context', function (): void {
    $context = $this->creator->authorizationContext();
    $this->creator->syncRoles(['admin']);
    $this->actingAs($this->creator->fresh())->withHeader('X-Office-Context', $context)->getJson(route('internal.projects.index'))->assertStatus(419);
    auth()->forgetGuards();
    $this->getJson(route('internal.projects.index'))->assertUnauthorized();
});

test('an existing incomplete archive stays incomplete when its attachment listing fails', function (): void {
    $link = ProjectEmailLink::factory()->for($this->project)->create([...$this->message, 'body_text' => 'Previous partial body']);
    $imap = Mockery::mock(ImapMailboxService::class);
    $imap->shouldReceive('folderUidValidity')->andReturn(100);
    $imap->shouldReceive('fetchMessageBody')->andReturn(['text' => 'New partial body', 'html' => null]);
    $imap->shouldReceive('fetchMessageAttachments')->andThrow(new RuntimeException('listing failed'));
    app()->instance(ImapMailboxService::class, $imap);
    $this->actingAs($this->creator)->postJson(route('projects.email.store', $this->project), $this->message)->assertUnprocessable();
    expect($link->fresh()->isArchived())->toBeFalse()->and($link->fresh()->body_text)->toBe('Previous partial body');
});

test('failed physical document write cannot complete an email archive', function (): void {
    $imap = ancillaryImap();
    $imap->shouldReceive('fetchMessageAttachments')->once()->andReturn([['part' => '2']]);
    $imap->shouldReceive('fetchAttachmentPart')->andReturn(['content' => 'bytes', 'filename' => 'file.txt', 'mime_type' => 'text/plain']);
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('put')->once()->andReturnFalse();
    $disk->shouldReceive('delete')->once()->andReturnTrue();
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    $this->actingAs($this->creator)->postJson(route('projects.email.store', $this->project), $this->message)->assertUnprocessable();
    expect($this->project->emailLinks()->count())->toBe(0)->and(Document::query()->count())->toBe(0);
});

test('document metadata failure removes the newly written file', function (): void {
    $userWithoutGroup = User::factory()->officeUser()->create();
    $userWithoutGroup->setRelation('group', Group::factory()->make());
    expect(fn () => app(DocumentStorageService::class)->storeFromContents($userWithoutGroup, 'secret', 'file.txt', 'text/plain'))->toThrow(QueryException::class);
    expect(Storage::disk('local')->allFiles())->toBe([])->and(Document::query()->count())->toBe(0);
});

test('live body is withheld if UIDVALIDITY changes during its retrieval', function (): void {
    $link = ProjectEmailLink::factory()->for($this->project)->create(['uidvalidity' => 100]);
    ancillaryImap(100, 101);
    $this->actingAs($this->creator)->getJson(route('internal.projects.email.body', [$this->project, $link]))->assertNotFound()->assertDontSee('Archived secret');
});

test('live body is withheld if membership is revoked while the IMAP request is in flight', function (): void {
    $link = ProjectEmailLink::factory()->for($this->project)->create(['uidvalidity' => 100]);
    $imap = ancillaryImap();
    $imap->shouldReceive('fetchMessageBody')->andReturnUsing(function (): array {
        $this->creator->forceFill(['group_id' => $this->outsider->group_id])->save();

        return ['text' => 'Withheld message', 'html' => null];
    })->once();
    $this->actingAs($this->creator)->getJson(route('internal.projects.email.body', [$this->project, $link]))->assertForbidden()->assertDontSee('Withheld message');
});

test('shared mail errors have localized messages rather than unresolved translation keys', function (string $locale): void {
    foreach (['archive_failed', 'mailbox_owner_required'] as $error) {
        $key = 'projects.email.errors.'.$error;
        expect(data_get(json_decode(file_get_contents(lang_path($locale.'.json')), true), $key))->toBeString()->not->toBe($key)->not->toBeEmpty();
    }
})->with(['bg', 'en']);

test('a temporary UIDVALIDITY failure never marks a live message as missing', function (bool $throws): void {
    $link = ProjectEmailLink::factory()->for($this->project)->create(['uidvalidity' => 100, 'last_verified_at' => now()->subDay()]);
    $verifiedAt = $link->last_verified_at;
    $imap = Mockery::mock(ImapMailboxService::class);
    $expectation = $imap->shouldReceive('folderUidValidity')->once();
    if ($throws) {
        $expectation->andThrow(new RuntimeException('private connection details'));
    } else {
        $expectation->andReturn(0);
    }
    $imap->shouldNotReceive('fetchMessageSummary', 'fetchMessageBody', 'fetchMessageAttachments');
    app()->instance(ImapMailboxService::class, $imap);
    $this->actingAs($this->creator)->getJson(route('internal.projects.email.index', $this->project))->assertUnprocessable()->assertDontSee('private connection details');
    expect($link->fresh()->status)->toBe(ProjectEmailLinkStatus::Active)
        ->and($link->fresh()->last_verified_at->equalTo($verifiedAt))->toBeTrue();
})->with([true, false]);
