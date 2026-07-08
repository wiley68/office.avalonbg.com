<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    skipUnlessFortifyFeature(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    get(route('password.request'))
        ->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        get(route('password.reset', $notification->token))
            ->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ])->assertSessionHasErrors('email');
});
