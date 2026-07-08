<?php

use App\Ai\Tools\ManageNotesTool;
use App\Models\Note;
use App\Models\User;
use App\Services\TextCryptoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request as AiToolRequest;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

test('note body is stored as plain text by default', function () {
    $user = User::factory()->create();
    $plain = 'Обикновен текст за бележката.';

    $note = Note::factory()->for($user)->create(['note' => $plain]);

    $raw = DB::table('notes')->where('id', $note->id)->value('note');
    expect(is_string($raw))->toBeTrue()
        ->and($raw)->toBe($plain);

    Sanctum::actingAs($user);

    $tool = new ManageNotesTool;
    $result = json_decode((string) $tool->handle(new AiToolRequest([
        'action' => 'show',
        'id' => $note->id,
    ])), true);

    expect($result['note'] ?? null)->toBe($plain);
});

test('note body can be saved as encrypted payload via agent tool', function () {
    $user = User::factory()->create();
    $plain = 'Тайно съдържание за криптиране.';
    $cipher = app(TextCryptoService::class)->encryptPlainText($plain);

    Sanctum::actingAs($user);

    $tool = new ManageNotesTool;
    $created = json_decode((string) $tool->handle(new AiToolRequest([
        'action' => 'create',
        'name' => 'Crypto',
        'note' => $cipher,
    ])), true);

    $noteId = (int) ($created['id'] ?? 0);
    expect($noteId)->toBeGreaterThan(0);

    $raw = DB::table('notes')->where('id', $noteId)->value('note');
    expect($raw)->toBe($cipher);

    $shown = json_decode((string) $tool->handle(new AiToolRequest([
        'action' => 'show',
        'id' => $noteId,
    ])), true);
    expect($shown['note'] ?? null)->toBe($cipher);
});

test('decrypt endpoint supports legacy app key payloads', function () {
    $user = User::factory()->create();
    $plain = 'Стар криптиран формат.';
    $legacyCipher = Crypt::encryptString($plain);

    actingAs($user);

    postJson('/dashboard/crypto/decrypt', [
        'text' => $legacyCipher,
    ])->assertOk()
        ->assertJsonPath('text', $plain);
});
