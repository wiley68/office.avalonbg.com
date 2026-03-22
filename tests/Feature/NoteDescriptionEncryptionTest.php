<?php

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

test('note body is encrypted at rest and decrypted via model and api', function () {
    $user = User::factory()->create();
    $plain = 'Тайно съдържание за бележката.';

    $note = Note::factory()->for($user)->create(['note' => $plain]);

    $raw = DB::table('notes')->where('id', $note->id)->value('note');
    expect(is_string($raw))->toBeTrue()
        ->and($raw)->not->toBe($plain);

    $note->refresh();
    expect($note->note)->toBe($plain);

    Sanctum::actingAs($user);

    getJson('/api/notes/' . $note->id)
        ->assertOk()
        ->assertJsonFragment(['note' => $plain]);
});
