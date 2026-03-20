<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

test('roles seeder creates admin and user roles', function () {
    seed(RolesSeeder::class);

    expect(Role::where('name', 'admin')->where('guard_name', 'web')->exists())->toBeTrue()
        ->and(Role::where('name', 'user')->where('guard_name', 'web')->exists())->toBeTrue();
});

test('database seeder creates admin user with admin role', function () {
    seed(DatabaseSeeder::class);

    $adminUser = User::query()->where('email', 'home@avalonbg.com')->first();

    expect($adminUser)->not->toBeNull()
        ->and($adminUser->name)->toBe('Администратор')
        ->and($adminUser->hasRole('admin'))->toBeTrue();
});
