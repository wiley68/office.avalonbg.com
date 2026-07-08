<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;

use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    skipUnlessFortifyFeature(Features::registration());
});

test('registration screen can be rendered', function () {
    get(route('register'))
        ->assertOk();
});

test('new users can register', function () {
    post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])
        ->assertRedirect(route('dashboard', absolute: false));

    assertAuthenticated();
});
