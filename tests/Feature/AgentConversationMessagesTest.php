<?php

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

test('owner receives conversation messages ordered', function () {
    $user = User::factory()->create();
    $convId = (string) Str::uuid();
    $msgUserId = (string) Str::uuid();
    $msgAsstId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $convId,
        'user_id' => $user->id,
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
        ->assertOk();
});

test('other user gets 404 for conversation messages', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $convId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $convId,
        'user_id' => $owner->id,
        'title' => 'Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($other);

    getJson("/dashboard/agent/conversations/{$convId}/messages")
        ->assertNotFound();
});
