<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('home page defaults to english locale', function () {
    get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'en')
            ->where('translations.welcome.sign_in', 'Sign in'));
});

test('locale can be switched and stored in session', function () {
    get(route('locale.update', ['locale' => 'bg']))
        ->assertRedirect(route('home'));

    get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'bg')
            ->where('translations.welcome.sign_in', 'Влез в системата'));
});

test('invalid locale returns not found', function () {
    get('/locale/fr')->assertNotFound();
});
