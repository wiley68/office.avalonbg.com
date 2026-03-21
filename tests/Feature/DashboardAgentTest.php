<?php

use App\Ai\Agents\ConversationalOfficeAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

test('guest cannot send message to agent', function () {
    postJson('/dashboard/agent', ['message' => 'Hello'])
        ->assertUnauthorized();
});

test('authenticated user gets validation error for empty message', function () {
    $user = User::factory()->create();

    actingAs($user);

    postJson('/dashboard/agent', ['message' => ''])
        ->assertUnprocessable();
});

test('authenticated user receives sse stream when ai is faked', function () {
    Ai::fakeAgent(ConversationalOfficeAgent::class, ['Тестов отговор от агента.']);

    $user = User::factory()->create();

    actingAs($user);

    $response = post('/dashboard/agent', ['message' => 'Здравей']);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $content = $response->streamedContent();
    expect($content)->toContain('text_delta');
    expect($content)->toContain('"type":"meta"');
    expect($content)->toContain('conversation_id');
    expect($content)->toContain('[DONE]');
});

test('invalid conversation id is rejected', function () {
    $user = User::factory()->create();

    actingAs($user);

    postJson('/dashboard/agent', [
        'message' => 'Hi',
        'conversation_id' => '00000000-0000-0000-0000-000000000000',
    ])->assertUnprocessable();
});
