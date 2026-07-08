<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

test('roles seeder creates profiler admin and user roles', function () {
    seed(RolesSeeder::class);

    expect(Role::where('name', 'profiler')->where('guard_name', 'web')->exists())->toBeTrue()
        ->and(Role::where('name', 'admin')->where('guard_name', 'web')->exists())->toBeTrue()
        ->and(Role::where('name', 'user')->where('guard_name', 'web')->exists())->toBeTrue();
});

test('database seeder creates seeded users with expected roles', function () {
    seed(DatabaseSeeder::class);

    $profiler = User::query()->where('email', 'ilko@avalonbg.com')->first();
    $admin = User::query()->where('email', 'home@avalonbg.com')->first();
    $user = User::query()->where('email', 'ilko.iv@gmail.com')->first();

    expect($profiler)->not->toBeNull()
        ->and($profiler->name)->toBe('Илко Профайлър')
        ->and($profiler->hasRole('profiler'))->toBeTrue()
        ->and($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Илко Администратор')
        ->and($admin->hasRole('admin'))->toBeTrue()
        ->and($user)->not->toBeNull()
        ->and($user->name)->toBe('Илко Иванов')
        ->and($user->hasRole('user'))->toBeTrue()
        ->and($profiler->hasEnabledTwoFactorAuthentication())->toBeFalse()
        ->and($admin->hasEnabledTwoFactorAuthentication())->toBeFalse()
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();
});
