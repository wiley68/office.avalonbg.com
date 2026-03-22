<?php

use App\Ai\Tools\ExportNotesToXlsxTool;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request as AiToolRequest;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('guest is redirected when accessing export download', function () {
    get('/dashboard/notes/export/550e8400-e29b-41d4-a716-446655440000')
        ->assertRedirect();
});

test('export tool returns download url and file downloads once for owner', function () {
    $user = User::factory()->create();
    Note::factory()->for($user)->count(2)->create();

    actingAs($user);

    $tool = app(ExportNotesToXlsxTool::class);
    $result = json_decode($tool->handle(new AiToolRequest([])), true);

    expect($result['ok'])->toBeTrue()
        ->and($result['note_count'])->toBe(2)
        ->and($result['download_url'])->toBeString();

    $first = get($result['download_url']);
    $first->assertOk();
    $first->assertHeaderContains('content-type', 'spreadsheetml');

    get($result['download_url'])->assertNotFound();
});

test('another user cannot download export with token', function () {
    $owner = User::factory()->create();
    Note::factory()->for($owner)->create();

    actingAs($owner);
    $tool = app(ExportNotesToXlsxTool::class);
    $result = json_decode($tool->handle(new AiToolRequest([])), true);

    $intruder = User::factory()->create();
    actingAs($intruder);

    get($result['download_url'])->assertNotFound();
});

test('export with zero notes still succeeds', function () {
    $user = User::factory()->create();
    actingAs($user);

    $tool = app(ExportNotesToXlsxTool::class);
    $result = json_decode($tool->handle(new AiToolRequest([])), true);

    expect($result['ok'])->toBeTrue()
        ->and($result['note_count'])->toBe(0);

    get($result['download_url'])->assertOk();
});

test('invalid token format returns not found', function () {
    actingAs(User::factory()->create());

    get('/dashboard/notes/export/not-a-uuid')->assertNotFound();
});
