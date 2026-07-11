<?php

use App\Mail\UserTwoFactorSetupMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('profiler', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');

    if (! Features::enabled(Features::twoFactorAuthentication())) {
        test()->markTestSkipped('Two factor authentication is not enabled.');
    }
});

test('profiler can enable two factor for admin user and send setup mail', function () {
    Mail::fake();

    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    $managedAdmin = User::factory()->create();
    $managedAdmin->assignRole('admin');

    actingAs($profiler)
        ->from(route('users.edit', $managedAdmin))
        ->post(route('users.two-factor.enable', $managedAdmin))
        ->assertRedirect(route('users.edit', $managedAdmin))
        ->assertInertiaFlashMissing('two_factor_manual_setup')
        ->assertInertiaFlashMissing('two_factor_notification');

    expect($managedAdmin->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();

    Mail::assertSent(UserTwoFactorSetupMail::class, function (UserTwoFactorSetupMail $mail) use ($managedAdmin) {
        return $mail->hasTo($managedAdmin->email) && $mail->forAdministrator === false;
    });
});

test('admin can enable two factor for office user', function () {
    Mail::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $officeUser = User::factory()->create();
    $officeUser->assignRole('user');

    actingAs($admin)
        ->post(route('users.two-factor.enable', $officeUser))
        ->assertRedirect();

    expect($officeUser->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

test('admin cannot enable two factor for another admin', function () {
    Mail::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('admin');

    actingAs($admin)
        ->post(route('users.two-factor.enable', $otherAdmin))
        ->assertForbidden();

    Mail::assertNothingSent();
});

test('profiler can disable two factor for admin user', function () {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    $managedAdmin = User::factory()->withTwoFactor()->create();
    $managedAdmin->assignRole('admin');

    actingAs($profiler)
        ->delete(route('users.two-factor.disable', $managedAdmin))
        ->assertRedirect();

    expect($managedAdmin->refresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

test('setup mail is sent to profiler when admin has no email', function () {
    Mail::fake();

    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    $managedAdmin = User::factory()->create(['email' => '']);
    $managedAdmin->assignRole('admin');

    actingAs($profiler)
        ->from(route('users.edit', $managedAdmin))
        ->post(route('users.two-factor.enable', $managedAdmin))
        ->assertRedirect(route('users.edit', $managedAdmin))
        ->assertInertiaFlash('two_factor_notification')
        ->assertInertiaFlash('two_factor_notification.type', 'sent_to_creator')
        ->assertInertiaFlash('two_factor_notification.sent_to', $profiler->email)
        ->assertInertiaFlashMissing('two_factor_manual_setup');

    Mail::assertSent(UserTwoFactorSetupMail::class, function (UserTwoFactorSetupMail $mail) use ($profiler, $managedAdmin) {
        return $mail->hasTo($profiler->email)
            && $mail->forAdministrator === true
            && $mail->user->is($managedAdmin);
    });

    Mail::assertSent(UserTwoFactorSetupMail::class, 1);
});

test('profiler sees manual setup flash when both mails fail', function () {
    Mail::shouldReceive('to')
        ->twice()
        ->andReturnSelf();

    Mail::shouldReceive('send')
        ->twice()
        ->andThrow(new RuntimeException('SMTP connection failed'));

    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    $managedAdmin = User::factory()->create();
    $managedAdmin->assignRole('admin');

    actingAs($profiler)
        ->from(route('users.edit', $managedAdmin))
        ->post(route('users.two-factor.enable', $managedAdmin))
        ->assertRedirect(route('users.edit', $managedAdmin))
        ->assertInertiaFlash('two_factor_manual_setup')
        ->assertInertiaFlash('two_factor_manual_setup.reason', 'The email could not be sent automatically.')
        ->assertInertiaFlashMissing('two_factor_notification');

    expect($managedAdmin->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

test('profiler can resend two factor setup mail for admin', function () {
    Mail::fake();

    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    $managedAdmin = User::factory()->withTwoFactor()->create();
    $managedAdmin->assignRole('admin');

    actingAs($profiler)
        ->post(route('users.two-factor.resend', $managedAdmin))
        ->assertRedirect()
        ->assertInertiaFlashMissing('two_factor_manual_setup')
        ->assertInertiaFlashMissing('two_factor_notification');

    Mail::assertSent(UserTwoFactorSetupMail::class, 1);
});

test('two factor setup mail contains qr code and recovery codes', function () {
    $user = User::factory()->withTwoFactor()->create();

    $mail = new UserTwoFactorSetupMail($user);
    $html = $mail->render();

    expect($html)
        ->toContain('data:image/svg+xml;base64,')
        ->toContain('recovery-code-1')
        ->toContain('Two-factor authentication');
});

test('admin fallback setup mail explains forwarding to user', function () {
    $admin = User::factory()->create(['name' => "Zoila D'Amore"]);
    $managedUser = User::factory()->withTwoFactor()->create([
        'email' => '',
        'name' => "O'Brien User",
    ]);

    $mail = new UserTwoFactorSetupMail($managedUser, forAdministrator: true, initiatorName: $admin->name);
    $html = $mail->render();

    expect($html)
        ->toContain(e($admin->name))
        ->toContain(e($managedUser->name))
        ->toContain('pass the details');
});

test('two factor manual setup flash uses bulgarian when locale is bg', function () {
    Mail::shouldReceive('to')
        ->twice()
        ->andReturnSelf();

    Mail::shouldReceive('send')
        ->twice()
        ->andThrow(new RuntimeException('SMTP connection failed'));

    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    $managedAdmin = User::factory()->create();
    $managedAdmin->assignRole('admin');

    actingAs($profiler)
        ->withSession(['locale' => 'bg'])
        ->from(route('users.edit', $managedAdmin))
        ->post(route('users.two-factor.enable', $managedAdmin))
        ->assertRedirect(route('users.edit', $managedAdmin))
        ->assertInertiaFlash(
            'two_factor_manual_setup.reason',
            'Имейлът не можа да бъде изпратен автоматично.',
        );
});

test('user edit page includes two factor status', function () {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    $managedAdmin = User::factory()->withTwoFactor()->create();
    $managedAdmin->assignRole('admin');

    actingAs($profiler)
        ->get(route('users.edit', $managedAdmin))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('users/Edit')
            ->where('user.two_factor_enabled', true));
});

test('user create page renders card layout', function () {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    actingAs($profiler)
        ->get(route('users.create'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('users/Create')
            ->where('manageableRole', 'admin'));
});
