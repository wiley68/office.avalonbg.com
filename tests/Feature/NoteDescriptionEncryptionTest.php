<?php

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

test('note description is encrypted at rest and decrypted via model and api', function () {
    $user = User::factory()->create();
    $plain = 'Тайно съдържание за бележката.';

    $note = Note::factory()->for($user)->create(['description' => $plain]);

    $raw = DB::table('notes')->where('id', $note->id)->value('description');
    expect(is_string($raw))->toBeTrue()
        ->and($raw)->not->toBe($plain);

    $note->refresh();
    expect($note->description)->toBe($plain);

    Sanctum::actingAs($user);

    getJson('/api/notes/' . $note->id)
        ->assertOk()
        ->assertJsonFragment(['description' => $plain]);
});
