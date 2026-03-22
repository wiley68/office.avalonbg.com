<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('inertia shares organization from config on dashboard', function () {
    config([
        'app.organization' => 'Тест ООД',
        'app.shop_url' => 'https://shop.example.test',
    ]);

    $user = User::factory()->create();

    actingAs($user);

    get(route('dashboard'))->assertInertia(
        fn(Assert $page) => $page
            ->has('organization')
            ->where('organization', 'Тест ООД')
            ->has('shopUrl')
            ->where('shopUrl', 'https://shop.example.test'),
    );
});
