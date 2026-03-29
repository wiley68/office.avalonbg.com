<?php

use App\Ai\Agents\ConversationalOfficeAgent;
use App\Enums\AgentContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Ai;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

test('guest cannot send message to warranties agent', function () {
    postJson('/dashboard/warranties/agent', ['message' => 'Hello'])
        ->assertUnauthorized();
});

test('authenticated user can open warranties agent page', function () {
    $user = User::factory()->create();

    actingAs($user);

    get(route('dashboard.warranties'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page->component('office/WarrantiesAgent'));
});

test('authenticated user receives sse stream from warranties agent when ai is faked', function () {
    Ai::fakeAgent(ConversationalOfficeAgent::class, ['Отговор от агента за гаранции.']);

    $user = User::factory()->create();

    actingAs($user);

    $response = post('/dashboard/warranties/agent', ['message' => 'Списък гаранционни карти']);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $content = $response->streamedContent();
    expect($content)->toContain('text_delta');
    expect($content)->toContain('"type":"meta"');
    expect($content)->toContain('conversation_id');
    expect($content)->toContain('[DONE]');

    $saved = DB::table('agent_conversations')
        ->where('user_id', $user->id)
        ->where('context', AgentContext::Warranties->value)
        ->exists();

    expect($saved)->toBeTrue();
});

test('conversation id from contacts context is rejected on warranties agent', function () {
    $user = User::factory()->create();
    $convId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $convId,
        'user_id' => $user->id,
        'context' => AgentContext::Contacts->value,
        'title' => 'Контакти',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    postJson('/dashboard/warranties/agent', [
        'message' => 'Hi',
        'conversation_id' => $convId,
    ])->assertUnprocessable();
});
