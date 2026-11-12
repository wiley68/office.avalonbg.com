<?php

use App\Models\Group;
use App\Models\ProfitType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    if (! app()->environment('testing')
        || config('database.default') !== 'sqlite'
        || config('database.connections.sqlite.database') !== ':memory:'
        || filled(config('database.connections.sqlite.url'))
        || ! config('database.connections.sqlite.foreign_key_constraints')) {
        throw new RuntimeException('Seeder tests require isolated SQLite memory storage with foreign keys.');
    }

    expect(Schema::getConnection()->selectOne('PRAGMA database_list')->file)->toBe('');
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    config(['seeding.profiler' => [
        'name' => 'Local Profiler',
        'email' => 'profiler@example.test',
        'password' => Str::random(24).'aA1!',
    ]]);
});

test('roles seeder creates exactly profiler admin and user roles', function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(RolesSeeder::class);

    expect(Role::query()->orderBy('name')->pluck('name')->all())->toBe(['admin', 'profiler', 'user'])
        ->and(Role::query()->where('guard_name', 'web')->count())->toBe(3);
});

test('database seeder creates configured groupless profiler without two factor', function (): void {
    $this->seed(DatabaseSeeder::class);
    $profiler = User::query()->where('email', config('seeding.profiler.email'))->firstOrFail();

    expect($profiler->name)->toBe('Local Profiler')
        ->and($profiler->hasRole('profiler'))->toBeTrue()
        ->and($profiler->group_id)->toBeNull()
        ->and($profiler->hasEnabledTwoFactorAuthentication())->toBeFalse()
        ->and($profiler->email_verified_at)->not->toBeNull()
        ->and(Hash::check(config('seeding.profiler.password'), $profiler->password))->toBeTrue()
        ->and(User::query()->count())->toBe(1)
        ->and(Group::query()->count())->toBe(0)
        ->and(ProfitType::query()->count())->toBe(0);
});

test('database seeder preserves an existing profilers password and two factor configuration', function (): void {
    $this->seed(DatabaseSeeder::class);
    $profiler = User::query()->firstOrFail();
    $existingPassword = $profiler->password;
    $profiler->forceFill([
        'two_factor_secret' => encrypt(Str::random(16)),
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => encrypt(json_encode(['existing-test-recovery-code'])),
    ])->save();
    $attributes = $profiler->getRawOriginal();
    config(['seeding.profiler.password' => Str::random(24).'aA1!', 'seeding.profiler.name' => 'Changed bootstrap name']);
    $this->seed(DatabaseSeeder::class);

    expect($profiler->fresh()->password === $existingPassword)->toBeTrue()
        ->and($profiler->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($profiler->fresh()->getRawOriginal())->toBe($attributes)
        ->and(User::query()->count())->toBe(1);
});

test('bootstrap refuses missing or malformed settings before writing data', function (string $field, ?string $value): void {
    config(['seeding.profiler.'.$field => $value]);

    expect(fn () => $this->seed(DatabaseSeeder::class))->toThrow(ValidationException::class);
    expect(User::query()->count())->toBe(0)
        ->and(Role::query()->count())->toBe(0)
        ->and(Group::query()->count())->toBe(0)
        ->and(ProfitType::query()->count())->toBe(0);
})->with([
    'missing name' => ['name', null],
    'blank name' => ['name', '   '],
    'oversized name' => ['name', str_repeat('n', 256)],
    'missing email' => ['email', null],
    'malformed email' => ['email', 'invalid-address'],
    'missing password' => ['password', null],
    'weak password' => ['password', 'short'],
]);

test('bootstrap cannot promote an existing office user', function (): void {
    $user = User::factory()->officeUser()->create(['email' => config('seeding.profiler.email')]);

    expect(fn () => $this->seed(DatabaseSeeder::class))->toThrow(LogicException::class);
    expect($user->fresh()->hasRole('user'))->toBeTrue()
        ->and($user->fresh()->hasRole('profiler'))->toBeFalse();
});

test('admin and profiler fixtures can have no group and office user fixtures have exactly one', function (): void {
    $this->seed(RolesSeeder::class);
    foreach (['admin', 'profiler'] as $role) {
        $account = User::factory()->create();
        $account->assignRole($role);
        expect($account->group_id)->toBeNull();
    }

    $officeUser = User::factory()->officeUser()->create();
    expect($officeUser->group->exists)->toBeTrue()
        ->and($officeUser->hasRole('user'))->toBeTrue();
});
