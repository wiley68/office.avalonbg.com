<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

test('registration page is not accessible', function () {
    get('/register')->assertNotFound();
});

test('guests cannot register users through public endpoint', function () {
    post('/register', [
        'name' => 'Guest User',
        'email' => 'guest@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::query()->where('email', 'guest@example.com')->exists())->toBeFalse();
});
