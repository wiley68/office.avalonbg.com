<?php

use App\Enums\AgentContext;
use App\Mail\AgentResponseMail;
use App\Models\User;
use App\Services\AgentMessageEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

test('guest cannot send assistant response by email', function () {
    postJson('/dashboard/agent/messages/' . Str::uuid()->toString() . '/email', [
        'email' => 'person@example.com',
    ])->assertUnauthorized();
});

test('owner can send assistant response by email', function () {
    config()->set('office.agent_email_delivery', 'sync');

    Mail::fake();

    $user = User::factory()->create();
    $conversationId = (string) Str::uuid();
    $messageId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'context' => AgentContext::Orchestrator->value,
        'title' => 'Email test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => $messageId,
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
        'agent' => 'TestAgent',
        'role' => 'assistant',
        'content' => 'Резултат за изпращане',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    postJson("/dashboard/agent/messages/{$messageId}/email", [
        'email' => 'person@gmail.com',
    ])->assertSuccessful();

    Mail::assertSent(AgentResponseMail::class, function (AgentResponseMail $mail) {
        return $mail->hasTo('person@gmail.com')
            && $mail->responseText === 'Резултат за изпращане';
    });
});

test('queue mode queues assistant response email', function () {
    config()->set('office.agent_email_delivery', 'queue');
    Mail::fake();

    $user = User::factory()->create();
    $conversationId = (string) Str::uuid();
    $messageId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'context' => AgentContext::Orchestrator->value,
        'title' => 'Email test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => $messageId,
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
        'agent' => 'TestAgent',
        'role' => 'assistant',
        'content' => 'Резултат за queue',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    postJson("/dashboard/agent/messages/{$messageId}/email", [
        'email' => 'person@gmail.com',
    ])->assertSuccessful();

    Mail::assertQueued(AgentResponseMail::class, function (AgentResponseMail $mail) {
        return $mail->hasTo('person@gmail.com')
            && $mail->responseText === 'Резултат за queue';
    });
});

test('email route is scoped by context', function () {
    Mail::fake();

    $user = User::factory()->create();
    $conversationId = (string) Str::uuid();
    $messageId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'context' => AgentContext::Notes->value,
        'title' => 'Notes email',
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

    postJson("/dashboard/agent/messages/{$messageId}/email", [
        'email' => 'person@gmail.com',
    ])->assertNotFound();

    postJson("/dashboard/notes/agent/messages/{$messageId}/email", [
        'email' => 'person@gmail.com',
    ])->assertSuccessful();
});

test('service can send direct content without assistant message lookup', function () {
    config()->set('office.agent_email_delivery', 'sync');
    Mail::fake();

    $user = User::factory()->create();

    app(AgentMessageEmailService::class)->send(
        user: $user,
        context: AgentContext::Orchestrator,
        email: 'person@gmail.com',
        messageId: null,
        subject: 'Тест тема',
        content: "Ред 1\nРед 2",
    );

    Mail::assertSent(AgentResponseMail::class, function (AgentResponseMail $mail) {
        return $mail->hasTo('person@gmail.com')
            && $mail->responseText === "Ред 1\nРед 2"
            && $mail->subjectText === 'Тест тема';
    });
});
