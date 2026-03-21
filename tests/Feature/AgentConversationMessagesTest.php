<?php

use App\Enums\AgentContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

test('guest cannot list conversation messages', function () {
    getJson('/dashboard/agent/conversations/' . Str::uuid()->toString() . '/messages')
        ->assertUnauthorized();
});

test('guest cannot list conversations', function () {
    getJson('/dashboard/agent/conversations')
        ->assertUnauthorized();
});

test('user receives empty conversations list', function () {
    $user = User::factory()->create();

    actingAs($user);

    getJson('/dashboard/agent/conversations')
        ->assertSuccessful()
        ->assertJsonPath('conversations', []);

    getJson('/dashboard/notes/agent/conversations')
        ->assertSuccessful()
        ->assertJsonPath('conversations', []);
});

test('user receives conversations ordered by updated_at for orchestrator context', function () {
    $user = User::factory()->create();
    $older = (string) Str::uuid();
    $newer = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        [
            'id' => $older,
            'user_id' => $user->id,
            'context' => AgentContext::Orchestrator->value,
            'title' => 'По-стар',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDay(),
        ],
        [
            'id' => $newer,
            'user_id' => $user->id,
            'context' => AgentContext::Orchestrator->value,
            'title' => 'По-нов',
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ],
    ]);

    actingAs($user);

    getJson('/dashboard/agent/conversations')
        ->assertSuccessful()
        ->assertJsonPath('conversations.0.id', $newer)
        ->assertJsonPath('conversations.0.title', 'По-нов')
        ->assertJsonPath('conversations.1.id', $older)
        ->assertJsonPath('conversations.1.title', 'По-стар');
});

test('conversation index is scoped by agent context', function () {
    $user = User::factory()->create();
    $orchestratorId = (string) Str::uuid();
    $notesId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        [
            'id' => $orchestratorId,
            'user_id' => $user->id,
            'context' => AgentContext::Orchestrator->value,
            'title' => 'Оркестратор',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => $notesId,
            'user_id' => $user->id,
            'context' => AgentContext::Notes->value,
            'title' => 'Бележки',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    actingAs($user);

    getJson('/dashboard/agent/conversations')
        ->assertSuccessful()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.id', $orchestratorId);

    getJson('/dashboard/notes/agent/conversations')
        ->assertSuccessful()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.id', $notesId);
});

test('owner receives conversation messages ordered', function () {
    $user = User::factory()->create();
    $convId = (string) Str::uuid();
    $msgUserId = (string) Str::uuid();
    $msgAsstId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $convId,
        'user_id' => $user->id,
        'context' => AgentContext::Orchestrator->value,
        'title' => 'Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        [
            'id' => $msgUserId,
            'conversation_id' => $convId,
            'user_id' => $user->id,
            'agent' => 'TestAgent',
            'role' => 'user',
            'content' => 'Hello',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ],
        [
            'id' => $msgAsstId,
            'conversation_id' => $convId,
            'user_id' => $user->id,
            'agent' => 'TestAgent',
            'role' => 'assistant',
            'content' => 'Hi there',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    actingAs($user);

    getJson("/dashboard/agent/conversations/{$convId}/messages")
        ->assertOk()
        ->assertJsonPath('messages.0.role', 'user')
        ->assertJsonPath('messages.0.content', 'Hello')
        ->assertJsonPath('messages.1.role', 'assistant')
        ->assertJsonPath('messages.1.content', 'Hi there');

    getJson("/dashboard/notes/agent/conversations/{$convId}/messages")
        ->assertNotFound();
});

test('conversation messages for notes context are only available on notes routes', function () {
    $user = User::factory()->create();
    $convId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $convId,
        'user_id' => $user->id,
        'context' => AgentContext::Notes->value,
        'title' => 'Notes chat',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid(),
        'conversation_id' => $convId,
        'user_id' => $user->id,
        'agent' => 'TestAgent',
        'role' => 'user',
        'content' => 'Hi',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    getJson("/dashboard/notes/agent/conversations/{$convId}/messages")
        ->assertOk()
        ->assertJsonPath('messages.0.content', 'Hi');

    getJson("/dashboard/agent/conversations/{$convId}/messages")
        ->assertNotFound();
});

test('other user gets 404 for conversation messages', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $convId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $convId,
        'user_id' => $owner->id,
        'context' => AgentContext::Orchestrator->value,
        'title' => 'Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($other);

    getJson("/dashboard/agent/conversations/{$convId}/messages")
        ->assertNotFound();
});
