<?php

use App\Models\Note;
use App\Models\User;
use App\Services\TextCryptoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
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

    getJson('/api/notes/'.$note->id)
        ->assertOk()
        ->assertJsonFragment(['note' => $plain]);
});

test('note body can be saved as encrypted payload and later decrypted', function () {
    $user = User::factory()->create();
    $plain = 'Тайно съдържание за криптиране.';
    $cipher = app(TextCryptoService::class)->encryptPlainText($plain);

    Sanctum::actingAs($user);

    $created = postJson('/api/notes', [
        'name' => 'Crypto',
        'description' => null,
        'note' => $cipher,
    ])->assertCreated();

    $noteId = (int) $created->json('data.id');

    $raw = DB::table('notes')->where('id', $noteId)->value('note');
    expect($raw)->toBe($cipher);

    getJson('/api/notes/'.$noteId)
        ->assertOk()
        ->assertJsonPath('data.note', $cipher);
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
