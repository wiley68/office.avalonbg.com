<?php

use App\Services\ImapMailboxService;

it('normalizes reply and forward subject prefixes', function () {
    $service = app(ImapMailboxService::class);
    $method = new ReflectionMethod(ImapMailboxService::class, 'normalizeSubject');
    $method->setAccessible(true);

    expect($method->invoke($service, 'Re: Project update'))->toBe('project update')
        ->and($method->invoke($service, 'Fwd: Re: Offer'))->toBe('offer')
        ->and($method->invoke($service, 'AW: Antwort'))->toBe('antwort');
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
