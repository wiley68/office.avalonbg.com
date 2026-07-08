<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\put;
use function Pest\Laravel\withSession;

uses(RefreshDatabase::class);

test('security page is displayed', function () {
    skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    actingAs($user);
    withSession(['auth.password_confirmed_at' => time()]);

    get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('canManageTwoFactor', true)
            ->where('twoFactorEnabled', true),
        );
});

test('security page requires password confirmation when enabled', function () {
    skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    $user = User::factory()->create();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    actingAs($user);

    get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));
});

test('security page does not require password confirmation when disabled', function () {
    skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    $user = User::factory()->create();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => false,
    ]);

    actingAs($user);

    get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security'),
        );
});

test('security page renders without two factor when feature is disabled', function () {
    skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    config(['fortify.features' => []]);

    $user = User::factory()->create();

    actingAs($user);

    get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('canManageTwoFactor', false)
            ->missing('twoFactorEnabled')
            ->missing('requiresConfirmation'),
        );
});

test('password can be updated', function () {
    $user = User::factory()->create();

    actingAs($user);

    from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'Password123!',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('NewPassword1!', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    actingAs($user);

    from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));
});
