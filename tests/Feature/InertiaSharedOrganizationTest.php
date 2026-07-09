<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('inertia shares organization from config on dashboard', function () {
    $organization = 'Тест ООД';
    $shopUrl = 'https://shop.example.test';
    $version = '2.5.0';

    config([
        'app.organization' => $organization,
        'app.shop_url' => $shopUrl,
        'app.version' => $version,
    ]);

    $user = User::factory()->create();

    actingAs($user);

    get(route('dashboard'))->assertInertia(
        fn (Assert $page) => $page
            ->has('organization')
            ->where('organization', $organization)
            ->has('shopUrl')
            ->where('shopUrl', $shopUrl)
            ->has('version')
            ->where('version', $version),
    );
});
