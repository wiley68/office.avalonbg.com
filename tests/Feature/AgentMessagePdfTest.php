<?php

use App\Enums\AgentContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

test('guest cannot download assistant message pdf', function () {
    getJson('/dashboard/agent/messages/'.Str::uuid()->toString().'/pdf')
        ->assertUnauthorized();
});

test('owner can download assistant message as pdf', function () {
    $user = User::factory()->create();
    $conversationId = (string) Str::uuid();
    $messageId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'context' => AgentContext::Orchestrator->value,
        'title' => 'PDF test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => $messageId,
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
        'agent' => 'TestAgent',
        'role' => 'assistant',
        'content' => "Ред 1\nРед 2 — PDF",
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    $response = get("/dashboard/agent/messages/{$messageId}/pdf");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('pdf route is scoped by context', function () {
    $user = User::factory()->create();
    $conversationId = (string) Str::uuid();
    $messageId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'context' => AgentContext::Notes->value,
        'title' => 'Notes pdf',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => $messageId,
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
        'agent' => 'TestAgent',
        'role' => 'assistant',
        'content' => 'Notes answer',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    get("/dashboard/agent/messages/{$messageId}/pdf")->assertNotFound();

    $response = get("/dashboard/notes/agent/messages/{$messageId}/pdf");
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});
