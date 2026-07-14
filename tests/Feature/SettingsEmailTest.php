<?php

use App\Models\User;
use App\Models\UserImapAccount;
use App\Services\ImapMailboxService;
use App\Support\Translations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;
use function Pest\Laravel\put;
use function Pest\Laravel\withoutVite;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('user', 'web');
});

test('office user can view email settings page', function () {
    withoutVite();

    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user)
        ->get(route('email.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Email')
            ->where('imapAccount', null));
});

test('office user can save imap account settings', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    put(route('email.update'), [
        'host' => 'imap.example.com',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => 'user@example.com',
        'password' => 'secret-password',
        'default_folder' => 'INBOX',
    ])->assertRedirect(route('email.edit'));

    expect($user->fresh()->imapAccount)
        ->not->toBeNull()
        ->host->toBe('imap.example.com')
        ->username->toBe('user@example.com')
        ->default_folder->toBe('INBOX');
});

test('office user cannot save starttls settings on port 143', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    put(route('email.update'), [
        'host' => 'mail.example.com',
        'port' => 143,
        'encryption' => 'tls',
        'username' => 'user@example.com',
        'password' => 'secret-password',
        'default_folder' => 'INBOX',
    ])->assertSessionHasErrors('encryption');
});

test('office user can test imap connection', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    UserImapAccount::factory()->for($user)->create([
        'host' => 'mail.example.com',
        'port' => 993,
        'encryption' => 'ssl',
    ]);

    mock(ImapMailboxService::class)
        ->shouldReceive('testConnection')
        ->once();

    actingAs($user)
        ->postJson(route('email.test'), [
            'host' => 'mail.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'user@example.com',
            'password' => null,
            'default_folder' => 'INBOX',
        ])
        ->assertOk()
        ->assertJsonPath('message', Translations::get('settings.email.test_success'));
});

test('office user cannot test starttls settings on port 143', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    UserImapAccount::factory()->for($user)->create();

    actingAs($user)
        ->postJson(route('email.test'), [
            'host' => 'mail.example.com',
            'port' => 143,
            'encryption' => 'tls',
            'username' => 'user@example.com',
            'password' => null,
            'default_folder' => 'INBOX',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['encryption']);
});

test('non office user cannot access email settings', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('email.edit'))
        ->assertForbidden();
});
