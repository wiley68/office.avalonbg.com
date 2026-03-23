<?php

use App\Ai\Tools\ManageNotesTool;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request as AiToolRequest;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

test('guest cannot list notes', function () {
    getJson('/api/notes')->assertUnauthorized();
});

test('session api request without referer authenticates with xhr header on same host', function () {
    $owner = User::factory()->create();
    Note::factory()->for($owner)->create(['name' => 'Mine', 'note' => 'A']);

    actingAs($owner);

    $response = getJson('/api/notes', [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['name' => 'Mine']);
});

test('authenticated user can list only own notes', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    Note::factory()->for($owner)->create(['name' => 'Mine', 'note' => 'A']);
    Note::factory()->for($other)->create(['name' => 'Theirs', 'note' => 'B']);

    Sanctum::actingAs($owner);

    $response = getJson('/api/notes');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['name' => 'Mine']);
    $response->assertJsonMissing(['name' => 'Theirs']);
});

test('notes index uses pagination metadata', function () {
    $user = User::factory()->create();

    Note::factory()->count(15)->for($user)->create();

    Sanctum::actingAs($user);

    $response = getJson('/api/notes?per_page=10&page=2');

    $response->assertOk();
    $response->assertJsonCount(5, 'data');
    $response->assertJsonPath('meta.current_page', 2);
    $response->assertJsonPath('meta.per_page', 10);
    $response->assertJsonPath('meta.last_page', 2);
    $response->assertJsonPath('meta.total', 15);
});

test('authenticated user can create a note', function () {
    Sanctum::actingAs(User::factory()->create());

    postJson('/api/notes', [
        'name' => 'Заглавие',
        'description' => 'Кратко',
        'note' => 'Пълно съдържание на бележката.',
    ])
        ->assertCreated()
        ->assertJsonFragment([
            'name' => 'Заглавие',
            'description' => 'Кратко',
            'note' => 'Пълно съдържание на бележката.',
        ]);
});

test('authenticated user can create a note without optional description', function () {
    Sanctum::actingAs(User::factory()->create());

    postJson('/api/notes', [
        'name' => 'Само заглавие',
        'note' => 'Съдържание.',
    ])
        ->assertCreated()
        ->assertJsonFragment([
            'name' => 'Само заглавие',
            'note' => 'Съдържание.',
        ])
        ->assertJsonPath('data.description', null);
});

test('user cannot view another users note', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $note = Note::factory()->for($owner)->create();

    Sanctum::actingAs($intruder);

    getJson('/api/notes/'.$note->id)->assertForbidden();
});

test('user cannot update another users note', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $note = Note::factory()->for($owner)->create();

    Sanctum::actingAs($intruder);

    putJson('/api/notes/'.$note->id, [
        'name' => 'Hack',
        'note' => 'No',
    ])->assertForbidden();
});

test('user cannot delete another users note', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $note = Note::factory()->for($owner)->create();

    Sanctum::actingAs($intruder);

    deleteJson('/api/notes/'.$note->id)->assertForbidden();
});

test('owner can show update and delete own note', function () {
    $owner = User::factory()->create();
    $note = Note::factory()->for($owner)->create([
        'name' => 'Original',
        'note' => 'Text',
    ]);

    Sanctum::actingAs($owner);

    getJson('/api/notes/'.$note->id)->assertOk()->assertJsonFragment(['name' => 'Original']);

    putJson('/api/notes/'.$note->id, [
        'name' => 'Updated',
        'note' => 'New body',
    ])->assertOk()->assertJsonFragment(['name' => 'Updated']);

    deleteJson('/api/notes/'.$note->id)->assertNoContent();

    getJson('/api/notes/'.$note->id)->assertNotFound();
});

test('manage notes tool returns error when not authenticated', function () {
    $tool = new ManageNotesTool;
    $result = $tool->handle(new AiToolRequest(['action' => 'list']));

    expect($result)->toContain('Няма логнат потребител');
});

test('manage notes tool lists notes for authenticated user', function () {
    $user = User::factory()->create();
    Note::factory()->for($user)->create(['name' => 'T1', 'note' => 'D1']);

    Sanctum::actingAs($user);

    $tool = new ManageNotesTool;
    $result = $tool->handle(new AiToolRequest(['action' => 'list']));

    expect($result)->toContain('T1')->toContain('D1');
});

test('manage notes tool returns total count', function () {
    $user = User::factory()->create();
    Note::factory()->count(3)->for($user)->create();

    Sanctum::actingAs($user);

    $tool = new ManageNotesTool;
    $result = $tool->handle(new AiToolRequest(['action' => 'count']));
    $decoded = json_decode((string) $result, true);

    expect($decoded)->toBeArray()
        ->and($decoded['total'] ?? null)->toBe(3);
});

test('manage notes tool supports pagination for list', function () {
    $user = User::factory()->create();
    Note::factory()->count(5)->for($user)->create();

    Sanctum::actingAs($user);

    $tool = new ManageNotesTool;
    $result = $tool->handle(new AiToolRequest([
        'action' => 'list',
        'page' => 2,
        'per_page' => 2,
    ]));
    $decoded = json_decode((string) $result, true);

    expect($decoded)->toBeArray()
        ->and($decoded['total'] ?? null)->toBe(5)
        ->and($decoded['returned'] ?? null)->toBe(2)
        ->and($decoded['page'] ?? null)->toBe(2)
        ->and($decoded['per_page'] ?? null)->toBe(2)
        ->and($decoded['last_page'] ?? null)->toBe(3)
        ->and(is_array($decoded['data'] ?? null))->toBeTrue();
});
