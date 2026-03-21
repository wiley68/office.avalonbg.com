<?php

use App\Enums\AgentContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\withoutVite;

uses(RefreshDatabase::class);

test('non admin cannot access agent statistics', function () {
    withoutVite();

    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');

    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    get('/dashboard/admin/statistics')
        ->assertForbidden();
});

test('admin can view aggregated agent statistics', function () {
    withoutVite();

    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $messageId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $admin->id,
        'context' => AgentContext::Orchestrator->value,
        'title' => 'Stats',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversationId = DB::table('agent_conversations')
        ->where('user_id', $admin->id)
        ->value('id');

    DB::table('agent_conversation_messages')->insert([
        'id' => $messageId,
        'conversation_id' => $conversationId,
        'user_id' => $admin->id,
        'agent' => 'App\\Ai\\Agents\\ConversationalOfficeAgent',
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

    DB::table('agent_message_feedback')->insert([
        'message_id' => $messageId,
        'user_id' => $admin->id,
        'feedback' => 'up',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($admin);

    get('/dashboard/admin/statistics?period=30d')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/AgentStatistics')
                ->where('period', '30d')
                ->has('summary_rows', 1)
                ->where('summary_rows.0.context', AgentContext::Orchestrator->value)
                ->where('summary_rows.0.up_count', 1)
                ->where('summary_rows.0.down_count', 0)
                ->where('summary_rows.0.total_feedback', 1)
                ->has('rows', 1)
                ->where('rows.0.context', AgentContext::Orchestrator->value)
                ->where('rows.0.up_count', 1)
                ->where('rows.0.down_count', 0)
                ->where('rows.0.total_feedback', 1)
        );
});
