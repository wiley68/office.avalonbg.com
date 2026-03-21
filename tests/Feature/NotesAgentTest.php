<?php

use App\Ai\Agents\ConversationalOfficeAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

test('guest cannot send message to notes agent', function () {
    postJson('/dashboard/notes/agent', ['message' => 'Hello'])
        ->assertUnauthorized();
});

test('authenticated user receives reply from notes agent when ai is faked', function () {
    Ai::fakeAgent(ConversationalOfficeAgent::class, ['Отговор от агента за бележки.']);

    $user = User::factory()->create();

    actingAs($user);

    postJson('/dashboard/notes/agent', ['message' => 'Списък бележки'])
        ->assertOk()
        ->assertJson([
            'reply' => 'Отговор от агента за бележки.',
        ])
        ->assertJsonStructure(['conversation_id']);
});
