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

test('database seeder creates profiler user without two factor', function () {
    seed(DatabaseSeeder::class);

    $profiler = User::query()->where('email', 'ilko@avalonbg.com')->first();

    expect($profiler)->not->toBeNull()
        ->and($profiler->name)->toBe('Илко Профайлър')
        ->and($profiler->hasRole('profiler'))->toBeTrue()
        ->and($profiler->hasEnabledTwoFactorAuthentication())->toBeFalse()
        ->and(User::query()->count())->toBe(1);
});
