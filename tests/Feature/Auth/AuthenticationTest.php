<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = get(route('login'));

    $response
        ->assertOk()
        ->assertHeader('Pragma', 'no-cache');

    expect($response->headers->get('Cache-Control'))
        ->toContain('no-store')
        ->toContain('no-cache')
        ->toContain('must-revalidate');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'Password123!',
    ])
        ->assertRedirect(route('dashboard', absolute: false));

    assertAuthenticated();
});

test('remember me is not used during login', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'remember_token' => null,
    ]);

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'Password123!',
        'remember' => true,
    ])
        ->assertRedirect(route('dashboard', absolute: false));

    assertAuthenticated();
    expect($user->fresh()->remember_token)->toBeNull();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    post(route('login'), [
        'email' => $user->email,
        'password' => 'Password123!',
        'remember' => true,
    ])
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->id)
        ->assertSessionHas('login.remember', false);

    assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    actingAs($user);

    post(route('logout'))
        ->assertRedirect(route('home'));

    assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
});
