<?php

use App\Support\Translations;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('home page defaults to english locale', function () {
    get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'en')
            ->where('appearance', 'system')
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

test('translations helper resolves nested english keys', function () {
    expect(Translations::get('users.admin.plural'))->toBe('Administrators')
        ->and(Translations::get('users.two_factor.email_send_failed'))->toBe('The email could not be sent automatically.')
        ->and(Translations::get('password.hint', ['min' => '9']))->toContain('9 characters');
});

test('translations helper resolves bulgarian keys', function () {
    expect(Translations::get('users.admin.plural', locale: 'bg'))->toBe('Администратори')
        ->and(Translations::get('users.two_factor.email_send_failed', locale: 'bg'))->toBe('Имейлът не можа да бъде изпратен автоматично.');
});

test('user translations are shared on inertia pages', function () {
    get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('translations.users.admin.create_title', 'New administrator')
            ->where('translations.users.tabs.profile', 'Profile'));
});
