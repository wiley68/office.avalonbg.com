<?php

use App\Ai\Agents\ConversationalOfficeAgent;
use App\Enums\AgentContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

test('guest cannot send message to notes agent', function () {
    postJson('/dashboard/notes/agent', ['message' => 'Hello'])
        ->assertUnauthorized();
});

test('authenticated user receives sse stream from notes agent when ai is faked', function () {
    Ai::fakeAgent(ConversationalOfficeAgent::class, ['Отговор от агента за бележки.']);

    $user = User::factory()->create();

    actingAs($user);

    $response = post('/dashboard/notes/agent', ['message' => 'Списък бележки']);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $content = $response->streamedContent();
    expect($content)->toContain('text_delta');
    expect($content)->toContain('"type":"meta"');
    expect($content)->toContain('conversation_id');
    expect($content)->toContain('[DONE]');
});

test('conversation id from orchestrator context is rejected on notes agent', function () {
    $user = User::factory()->create();
    $convId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $convId,
        'user_id' => $user->id,
        'context' => AgentContext::Orchestrator->value,
        'title' => 'Табло',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    postJson('/dashboard/notes/agent', [
        'message' => 'Hi',
        'conversation_id' => $convId,
    ])->assertUnprocessable();
});
