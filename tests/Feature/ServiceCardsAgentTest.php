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

test('guest cannot send message to service cards agent', function (): void {
    postJson('/dashboard/service-cards/agent', ['message' => 'Hello'])
        ->assertUnauthorized();
});

test('authenticated user receives sse stream from service cards agent when ai is faked', function (): void {
    Ai::fakeAgent(ConversationalOfficeAgent::class, ['Отговор от агента за сервизни карти.']);

    $user = User::factory()->create();

    actingAs($user);

    $response = post('/dashboard/service-cards/agent', ['message' => 'Списък сервизни карти']);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $content = $response->streamedContent();
    expect($content)->toContain('text_delta');
    expect($content)->toContain('"type":"meta"');
    expect($content)->toContain('conversation_id');
    expect($content)->toContain('[DONE]');

    $saved = DB::table('agent_conversations')
        ->where('user_id', $user->id)
        ->where('context', AgentContext::ServiceCards->value)
        ->exists();

    expect($saved)->toBeTrue();
});

test('conversation id from warranties context is rejected on service cards agent', function (): void {
    $user = User::factory()->create();
    $convId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $convId,
        'user_id' => $user->id,
        'context' => AgentContext::Warranties->value,
        'title' => 'Гаранции',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    postJson('/dashboard/service-cards/agent', [
        'message' => 'Hi',
        'conversation_id' => $convId,
    ])->assertUnprocessable();
});
