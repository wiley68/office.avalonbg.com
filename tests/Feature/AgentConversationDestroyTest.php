<?php

use App\Enums\AgentContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;

uses(RefreshDatabase::class);

test('guest cannot delete all conversations', function () {
    deleteJson('/dashboard/agent/conversations')->assertUnauthorized();
});

test('user can delete all orchestrator conversations and related rows', function () {
    $user = User::factory()->create();
    $convA = (string) Str::uuid();
    $convB = (string) Str::uuid();
    $msgA = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        [
            'id' => $convA,
            'user_id' => $user->id,
            'context' => AgentContext::Orchestrator->value,
            'title' => 'A',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => $convB,
            'user_id' => $user->id,
            'context' => AgentContext::Orchestrator->value,
            'title' => 'B',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => $msgA,
        'conversation_id' => $convA,
        'user_id' => $user->id,
        'agent' => 'Test',
        'role' => 'assistant',
        'content' => 'Hi',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_message_feedback')->insert([
        'message_id' => $msgA,
        'user_id' => $user->id,
        'feedback' => 'up',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $notesConv = (string) Str::uuid();
    DB::table('agent_conversations')->insert([
        'id' => $notesConv,
        'user_id' => $user->id,
        'context' => AgentContext::Notes->value,
        'title' => 'Notes only',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    deleteJson('/dashboard/agent/conversations')->assertSuccessful()->assertJson([
        'ok' => true,
        'deleted_conversations' => 2,
    ]);

    expect(DB::table('agent_conversations')->where('user_id', $user->id)->where('context', AgentContext::Orchestrator->value)->count())->toBe(0);
    expect(DB::table('agent_conversations')->where('id', $notesConv)->exists())->toBeTrue();
    expect(DB::table('agent_conversation_messages')->where('conversation_id', $convA)->exists())->toBeFalse();
    expect(DB::table('agent_message_feedback')->where('message_id', $msgA)->exists())->toBeFalse();
});

test('notes delete all only affects notes context', function () {
    $user = User::factory()->create();
    $orch = (string) Str::uuid();
    $notes = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        [
            'id' => $orch,
            'user_id' => $user->id,
            'context' => AgentContext::Orchestrator->value,
            'title' => 'Orch',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => $notes,
            'user_id' => $user->id,
            'context' => AgentContext::Notes->value,
            'title' => 'N',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    actingAs($user);

    deleteJson('/dashboard/notes/agent/conversations')->assertSuccessful();

    expect(DB::table('agent_conversations')->where('id', $orch)->exists())->toBeTrue();
    expect(DB::table('agent_conversations')->where('id', $notes)->exists())->toBeFalse();
});
