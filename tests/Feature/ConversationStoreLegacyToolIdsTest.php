<?php

use App\Ai\Agents\ConversationalOfficeAgent;
use App\Enums\AgentContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('legacy assistant rows with null result_id get call_id fallback for OpenAI', function (): void {
    $user = User::factory()->create();
    actingAs($user);

    $convId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $convId,
        'user_id' => $user->id,
        'context' => AgentContext::Notes->value,
        'title' => 'Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $callId = 'call_legacy_abc123';
    $toolCallsJson = json_encode([
        [
            'id' => $callId,
            'name' => 'manage_notes',
            'arguments' => ['action' => 'list'],
            'result_id' => null,
            'reasoning_id' => null,
            'reasoning_summary' => null,
        ],
    ]);
    $toolResultsJson = json_encode([
        [
            'id' => $callId,
            'name' => 'manage_notes',
            'arguments' => [],
            'result' => '{"ok":true}',
            'result_id' => null,
        ],
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid(),
        'conversation_id' => $convId,
        'user_id' => $user->id,
        'agent' => ConversationalOfficeAgent::class,
        'role' => 'assistant',
        'content' => '',
        'attachments' => '[]',
        'tool_calls' => $toolCallsJson,
        'tool_results' => $toolResultsJson,
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    /** @var ConversationStore $store */
    $store = app(ConversationStore::class);
    $messages = $store->getLatestConversationMessages($convId, 50);

    $assistant = $messages->first(fn ($m) => $m instanceof AssistantMessage);
    expect($assistant)->not->toBeNull();
    assert($assistant instanceof AssistantMessage);
    $firstToolCall = $assistant->toolCalls->first();
    expect($firstToolCall)->not->toBeNull();
    assert($firstToolCall instanceof ToolCall);
    expect($firstToolCall->resultId)->toBe($callId);

    $toolBlock = $messages->first(fn ($m) => $m instanceof ToolResultMessage);
    expect($toolBlock)->not->toBeNull();
    assert($toolBlock instanceof ToolResultMessage);
    $firstToolResult = $toolBlock->toolResults->first();
    expect($firstToolResult)->not->toBeNull();
    assert($firstToolResult instanceof ToolResult);
    expect($firstToolResult->resultId)->toBe($callId);
});
