<?php

use App\Enums\AgentContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

test('guest cannot rate assistant response', function () {
    postJson('/dashboard/agent/messages/'.Str::uuid()->toString().'/feedback', [
        'feedback' => 'up',
    ])->assertUnauthorized();
});

test('user can set and update feedback for own assistant response', function () {
    $user = User::factory()->create();
    $conversationId = (string) Str::uuid();
    $messageId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'context' => AgentContext::Orchestrator->value,
        'title' => 'Feedback test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => $messageId,
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
        'agent' => 'TestAgent',
        'role' => 'assistant',
        'content' => 'Answer',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    postJson("/dashboard/agent/messages/{$messageId}/feedback", [
        'feedback' => 'up',
    ])->assertSuccessful()
        ->assertJsonPath('feedback', 'up');

    postJson("/dashboard/agent/messages/{$messageId}/feedback", [
        'feedback' => 'down',
    ])->assertSuccessful()
        ->assertJsonPath('feedback', 'down');

    expect(DB::table('agent_message_feedback')
        ->where('message_id', $messageId)
        ->where('user_id', $user->id)
        ->value('feedback'))->toBe('down');
});

test('feedback is rejected for non assistant messages', function () {
    $user = User::factory()->create();
    $conversationId = (string) Str::uuid();
    $messageId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'context' => AgentContext::Orchestrator->value,
        'title' => 'Feedback test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => $messageId,
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
        'agent' => 'TestAgent',
        'role' => 'user',
        'content' => 'Question',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    postJson("/dashboard/agent/messages/{$messageId}/feedback", [
        'feedback' => 'up',
    ])->assertNotFound();
});

test('feedback route is scoped by context', function () {
    $user = User::factory()->create();
    $conversationId = (string) Str::uuid();
    $messageId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'context' => AgentContext::Notes->value,
        'title' => 'Notes feedback',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => $messageId,
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
        'agent' => 'TestAgent',
        'role' => 'assistant',
        'content' => 'Answer',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    postJson("/dashboard/agent/messages/{$messageId}/feedback", [
        'feedback' => 'up',
    ])->assertNotFound();

    postJson("/dashboard/notes/agent/messages/{$messageId}/feedback", [
        'feedback' => 'up',
    ])->assertSuccessful();
});
