<?php

use App\Services\ImapMailboxService;

it('normalizes reply and forward subject prefixes', function () {
    $service = app(ImapMailboxService::class);
    $method = new ReflectionMethod(ImapMailboxService::class, 'normalizeSubject');
    $method->setAccessible(true);

    expect($method->invoke($service, 'Re: Project update'))->toBe('project update')
        ->and($method->invoke($service, 'Fwd: Re: Offer'))->toBe('offer')
        ->and($method->invoke($service, 'AW: Antwort'))->toBe('antwort')
        ->and($method->invoke($service, 'Re[2]: DEM-18992 Обновяване'))->toBe('dem-18992 обновяване')
        ->and($method->invoke($service, 'RE[4]: BR Avalon'))->toBe('br avalon')
        ->and($method->invoke($service, 'Re[6]: Re: #295179 RFO - Control panel'))->toBe('#295179 rfo - control panel');
});

it('builds conversation keys from ticket references and normalized subjects', function () {
    $service = app(ImapMailboxService::class);
    $method = new ReflectionMethod(ImapMailboxService::class, 'conversationKey');
    $method->setAccessible(true);

    expect($method->invoke($service, 'Re[3]: DEM-18992 Обновяване на модули'))->toBe('ticket:dem-18992')
        ->and($method->invoke($service, '#295179 RFO - Control panel'))->toBe('ticket:#295179')
        ->and($method->invoke($service, 'Re: BR Avalon'))->toBe($method->invoke($service, 'BR Avalon'));
});

it('merges separate threads that share the same conversation key', function () {
    $service = app(ImapMailboxService::class);
    $method = new ReflectionMethod(ImapMailboxService::class, 'mergeThreadsByConversationKey');
    $method->setAccessible(true);

    $message = fn (int $uid, string $subject, string $sentAt): array => [
        'uid' => $uid,
        'uidvalidity' => 1,
        'subject' => $subject,
        'from_name' => null,
        'from_address' => null,
        'sent_at' => $sentAt,
    ];

    $threads = $method->invoke($service, [
        [
            'id' => 'imap-1201',
            'grouping' => 'imap_thread',
            'message_count' => 1,
            'subject' => 'BR Avalon',
            'latest_sent_at' => '2026-07-10T10:00:00Z',
            'messages' => [$message(1201, 'BR Avalon', '2026-07-10T10:00:00Z')],
        ],
        [
            'id' => 'imap-1202',
            'grouping' => 'imap_thread',
            'message_count' => 1,
            'subject' => 'Re: BR Avalon',
            'latest_sent_at' => '2026-07-11T12:00:00Z',
            'messages' => [$message(1202, 'Re: BR Avalon', '2026-07-11T12:00:00Z')],
        ],
    ]);

    expect($threads)->toHaveCount(1)
        ->and($threads[0]['grouping'])->toBe('merged')
        ->and($threads[0]['message_count'])->toBe(2)
        ->and($threads[0]['messages'][0]['uid'])->toBe(1201)
        ->and($threads[0]['messages'][1]['uid'])->toBe(1202);
});

it('parses imap thread parent map from thread data', function () {
    $service = app(ImapMailboxService::class);
    $method = new ReflectionMethod(ImapMailboxService::class, 'parseImapThreadParents');
    $method->setAccessible(true);

    $parents = $method->invoke($service, "10\n20\t10\n30\t10\n40\t20");

    expect($parents)->toBe([
        10 => null,
        20 => 10,
        30 => 10,
        40 => 20,
    ]);
});

it('resolves thread root uid from parent chain', function () {
    $service = app(ImapMailboxService::class);
    $resolve = new ReflectionMethod(ImapMailboxService::class, 'resolveThreadRootUid');
    $resolve->setAccessible(true);

    $parents = [
        10 => null,
        20 => 10,
        30 => 10,
        40 => 20,
    ];

    expect($resolve->invoke($service, 40, $parents))->toBe(10)
        ->and($resolve->invoke($service, 30, $parents))->toBe(10)
        ->and($resolve->invoke($service, 10, $parents))->toBe(10);
});
