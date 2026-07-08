<?php

use App\Ai\Tools\ManageNotesTool;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request as AiToolRequest;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

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

test('manage notes tool owner can show update and delete own note', function () {
    $owner = User::factory()->create();
    $note = Note::factory()->for($owner)->create([
        'name' => 'Original',
        'note' => 'Text',
    ]);

    Sanctum::actingAs($owner);

    $tool = new ManageNotesTool;

    $show = json_decode((string) $tool->handle(new AiToolRequest([
        'action' => 'show',
        'id' => $note->id,
    ])), true);
    expect($show['name'] ?? null)->toBe('Original');

    $update = json_decode((string) $tool->handle(new AiToolRequest([
        'action' => 'update',
        'id' => $note->id,
        'name' => 'Updated',
        'note' => 'New body',
    ])), true);
    expect($update['name'] ?? null)->toBe('Updated');

    $delete = json_decode((string) $tool->handle(new AiToolRequest([
        'action' => 'delete',
        'id' => $note->id,
    ])), true);
    expect($delete['ok'] ?? null)->toBeTrue();

    expect(Note::query()->find($note->id))->toBeNull();
});

test('manage notes tool user cannot show another users note', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $note = Note::factory()->for($owner)->create();

    Sanctum::actingAs($intruder);

    $tool = new ManageNotesTool;
    $result = $tool->handle(new AiToolRequest([
        'action' => 'show',
        'id' => $note->id,
    ]));

    expect($result)->toContain('error');
});
