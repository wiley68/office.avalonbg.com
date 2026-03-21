<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;

use function Pest\Laravel\actingAs;
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

test('authenticated user receives reply when ai is faked', function () {
    Ai::fakeAgent(AnonymousAgent::class, ['Тестов отговор от агента.']);

    $user = User::factory()->create();

    actingAs($user);

    postJson('/dashboard/agent', ['message' => 'Здравей'])
        ->assertOk()
        ->assertJson([
            'reply' => 'Тестов отговор от агента.',
        ]);
});
