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

beforeEach(function (): void {
    Role::findOrCreate('profiler', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');
});

test('profiler cannot access agent statistics', function () {
    withoutVite();

    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    actingAs($profiler);

    get('/dashboard/admin/statistics')
        ->assertForbidden();
});

test('office user can view aggregated agent statistics', function () {
    withoutVite();

    $user = User::factory()->create();
    $user->assignRole('user');
    $messageId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'context' => AgentContext::Orchestrator->value,
        'title' => 'Stats',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversationId = DB::table('agent_conversations')
        ->where('user_id', $user->id)
        ->value('id');

    DB::table('agent_conversation_messages')->insert([
        'id' => $messageId,
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
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
        'user_id' => $user->id,
        'feedback' => 'up',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    $period = '30d';

    get('/dashboard/admin/statistics?period='.$period)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/AgentStatistics')
                ->where('period', $period)
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

test('admin can view aggregated agent statistics', function () {
    withoutVite();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    $period = '30d';

    get('/dashboard/admin/statistics?period='.$period)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/AgentStatistics'));
});
