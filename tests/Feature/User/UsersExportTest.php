<?php

use App\Models\User;
use App\Services\UsersXlsxExportService;
use App\Support\EncryptedSevenZipArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('profiler', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');
});

test('profiler export includes only admin users', function () {
    $profiler = User::factory()->create(['email' => 'profiler-export@test']);
    $profiler->assignRole('profiler');

    $admin = User::factory()->create(['email' => 'admin-export@test']);
    $admin->assignRole('admin');

    $officeUser = User::factory()->create(['email' => 'user-export@test']);
    $officeUser->assignRole('user');

    $export = app(UsersXlsxExportService::class);
    $emails = $export->exportableUsers($profiler)->pluck('email');

    expect($emails)
        ->toContain('admin-export@test')
        ->not->toContain('profiler-export@test', 'user-export@test');
});

test('admin export includes only office users', function () {
    $admin = User::factory()->create(['email' => 'admin-actor@test']);
    $admin->assignRole('admin');

    $officeUser = User::factory()->create(['email' => 'office-one@test']);
    $officeUser->assignRole('user');

    $otherAdmin = User::factory()->create(['email' => 'other-admin@test']);
    $otherAdmin->assignRole('admin');

    $export = app(UsersXlsxExportService::class);
    $emails = $export->exportableUsers($admin)->pluck('email');

    expect($emails)
        ->toContain('office-one@test')
        ->not->toContain('admin-actor@test', 'other-admin@test');
});

test('users export rows exclude sensitive fields', function () {
    $admin = User::factory()->create([
        'email' => 'office-export@test',
        'password' => 'SecretPassword123!',
        'must_change_password' => true,
        'two_factor_secret' => encrypt('secret-value'),
        'remember_token' => 'remember-me-token',
    ]);
    $admin->assignRole('user');

    $actor = User::factory()->create();
    $actor->assignRole('admin');

    $rows = app(UsersXlsxExportService::class)->buildRows($actor);
    $flat = collect($rows)->flatten()->implode('|');

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toHaveCount(6)
        ->and($flat)->not->toContain('SecretPassword123!')
        ->and($flat)->not->toContain('remember-me-token')
        ->and($flat)->not->toContain('secret-value');
});

test('office user cannot export users', function () {
    $officeUser = User::factory()->create();
    $officeUser->assignRole('user');

    actingAs($officeUser)
        ->postJson(route('users.export'), [
            'password' => 'ExportPass1!',
            'password_confirmation' => 'ExportPass1!',
        ])
        ->assertForbidden();
});

test('profiler can export users when 7z is available', function () {
    $archive = app(EncryptedSevenZipArchive::class);
    if (! $archive->isAvailable()) {
        $this->markTestSkipped('7z не е наличен на сървъра.');
    }

    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    $managedAdmin = User::factory()->create();
    $managedAdmin->assignRole('admin');

    $response = actingAs($profiler)
        ->postJson(route('users.export'), [
            'password' => 'ExportPass1!',
            'password_confirmation' => 'ExportPass1!',
        ])
        ->assertOk()
        ->assertDownload();

    expect($response->headers->get('content-disposition'))
        ->toMatch('/users_admin_.*\.7z/');
});

test('admin can export users when 7z is available', function () {
    $archive = app(EncryptedSevenZipArchive::class);
    if (! $archive->isAvailable()) {
        $this->markTestSkipped('7z не е наличен на сървъра.');
    }

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $officeUser = User::factory()->create();
    $officeUser->assignRole('user');

    $response = actingAs($admin)
        ->postJson(route('users.export'), [
            'password' => 'ExportPass1!',
            'password_confirmation' => 'ExportPass1!',
        ])
        ->assertOk()
        ->assertDownload();

    expect($response->headers->get('content-disposition'))
        ->toMatch('/users_user_.*\.7z/');
});
