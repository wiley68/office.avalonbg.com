<?php

use App\Enums\AuditEventSource;
use App\Enums\AuditEventType;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\EncryptedSevenZipArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('profiler', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');
});

function createAuditLog(array $overrides = []): AuditLog
{
    return AuditLog::create(array_merge([
        'occurred_at' => now(),
        'event_type' => AuditEventType::LoginSuccess,
        'event_source' => AuditEventSource::Office,
        'is_success' => true,
        'user_id' => null,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'description' => json_encode([
            ['поле' => 'имейл', 'стойност' => 'test@example.com'],
        ], JSON_UNESCAPED_UNICODE),
    ], $overrides));
}

test('profiler can view audit logs index', function () {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    actingAs($profiler)
        ->get(route('audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('audit-logs/Index'));
});

test('profiler can list audit logs via internal api', function () {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    createAuditLog([
        'user_email' => $profiler->email,
        'user_name' => $profiler->name,
        'user_id' => $profiler->id,
    ]);

    actingAs($profiler)
        ->getJson(route('internal.audit-logs.index', ['search' => $profiler->email]))
        ->assertOk()
        ->assertJsonPath('data.0.event_type', 'login_success')
        ->assertJsonPath('data.0.event_type_label', 'Successful login')
        ->assertJsonPath('data.0.event_source_label', 'Office')
        ->assertJsonPath('total', 1);
});

test('audit log api returns bulgarian labels when locale is bg', function () {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    createAuditLog([
        'user_email' => $profiler->email,
        'user_name' => $profiler->name,
        'user_id' => $profiler->id,
    ]);

    actingAs($profiler)
        ->withSession(['locale' => 'bg'])
        ->getJson(route('internal.audit-logs.index', ['search' => $profiler->email]))
        ->assertOk()
        ->assertJsonPath('data.0.event_type_label', 'Успешен вход')
        ->assertJsonPath('data.0.event_source_label', 'Офис');
});

test('admin cannot access audit logs', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin)
        ->get(route('audit-logs.index'))
        ->assertForbidden();

    actingAs($admin)
        ->getJson(route('internal.audit-logs.index'))
        ->assertForbidden();
});

test('user cannot access audit logs', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user)
        ->get(route('audit-logs.index'))
        ->assertForbidden();

    actingAs($user)
        ->getJson(route('internal.audit-logs.index'))
        ->assertForbidden();
});

test('successful login creates audit log entry', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'Password123!',
    ])->assertRedirect(route('dashboard', absolute: false));

    $log = AuditLog::query()
        ->where(AuditLog::COLUMN_EVENT_TYPE, AuditEventType::LoginSuccess)
        ->where(AuditLog::COLUMN_USER_EMAIL, $user->email)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->is_success)->toBeTrue()
        ->and($log->event_source)->toBe(AuditEventSource::Office);

    $details = json_decode($log->description, true);
    expect($details)->toBeArray()
        ->and(collect($details)->pluck('поле'))->not->toContain('password');
});

test('failed login creates audit log entry without password', function () {
    $user = User::factory()->create();

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $log = AuditLog::query()
        ->where(AuditLog::COLUMN_EVENT_TYPE, AuditEventType::LoginFailed)
        ->where(AuditLog::COLUMN_USER_EMAIL, $user->email)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->is_success)->toBeFalse();

    $encoded = $log->description;
    expect($encoded)->not->toContain('wrong-password')
        ->and($encoded)->not->toContain('password')
        ->and($encoded)->toContain('invalid_credentials');
});

test('profiler can delete single audit log', function () {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    $log = createAuditLog();

    actingAs($profiler)
        ->delete(route('audit-logs.destroy', $log))
        ->assertRedirect();

    assertDatabaseMissing('audit_logs', ['id' => $log->id]);
});

test('profiler can delete audit logs in bulk', function () {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    $first = createAuditLog();
    $second = createAuditLog(['user_email' => 'other@example.com']);

    actingAs($profiler)
        ->delete(route('audit-logs.destroy-bulk'), [
            'ids' => [$first->id, $second->id],
        ])
        ->assertRedirect();

    assertDatabaseMissing('audit_logs', ['id' => $first->id]);
    assertDatabaseMissing('audit_logs', ['id' => $second->id]);
});

test('admin cannot delete audit logs', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $log = createAuditLog();

    actingAs($admin)
        ->delete(route('audit-logs.destroy', $log))
        ->assertForbidden();
});

test('profiler can export audit logs when 7z is available', function () {
    $archive = app(EncryptedSevenZipArchive::class);
    if (! $archive->isAvailable()) {
        $this->markTestSkipped('7z не е наличен на сървъра.');
    }

    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    $dateFrom = now()->subDays(7)->toDateString();
    $dateTo = now()->toDateString();

    createAuditLog([
        'occurred_at' => now()->subDays(2),
        'user_email' => $profiler->email,
    ]);

    $response = actingAs($profiler)
        ->postJson(route('audit-logs.export'), [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'password' => 'ExportPass1!',
            'password_confirmation' => 'ExportPass1!',
        ])
        ->assertOk()
        ->assertDownload();

    expect($response->headers->get('content-disposition'))
        ->toMatch('/audit_logs_'.preg_quote($dateFrom, '/').'_'.preg_quote($dateTo, '/').'_.*\.7z/');
});

test('prune command deletes audit logs older than configured retention', function () {
    config(['retention.audit_logs_years' => 1]);

    $old = createAuditLog(['occurred_at' => now()->subYears(2)]);
    $recent = createAuditLog(['occurred_at' => now()->subMonths(2)]);

    artisan('audit-logs:prune')->assertSuccessful();

    assertDatabaseMissing('audit_logs', ['id' => $old->id]);
    expect(AuditLog::query()->whereKey($recent->id)->exists())->toBeTrue();
});
