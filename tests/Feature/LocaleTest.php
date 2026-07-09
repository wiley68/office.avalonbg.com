<?php

use App\Models\User;
use App\Support\Translations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('profiler', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');
});

test('home page defaults to english locale', function () {
    get(route('home'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('locale', 'en')
            ->where('appearance', 'system')
            ->where('translations.welcome.sign_in', 'Sign in'));
});

test('locale can be switched and stored in session', function () {
    get(route('locale.update', ['locale' => 'bg']))
        ->assertRedirect(route('home'));

    get(route('home'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('locale', 'bg')
            ->where('translations.welcome.sign_in', 'Влез в системата'));
});

test('invalid locale returns not found', function () {
    get('/locale/fr')->assertNotFound();
});

test('translations helper resolves nested english keys', function () {
    expect(Translations::get('nav.users'))->toBe('Users')
        ->and(Translations::get('users.admin.plural'))->toBe('Administrators')
        ->and(Translations::get('users.two_factor.email_send_failed'))->toBe('The email could not be sent automatically.')
        ->and(Translations::get('password.hint', ['min' => '9']))->toContain('9 characters')
        ->and(Translations::get('common.table.page_of', ['current' => '2', 'total' => '5']))->toBe('Page 2 of 5')
        ->and(Translations::get('common.confirm'))->toBe('Confirm')
        ->and(Translations::get('common.confirm_delete'))->toBe('Delete');
});

test('translations helper resolves bulgarian keys', function () {
    expect(Translations::get('nav.users', locale: 'bg'))->toBe('Потребители')
        ->and(Translations::get('users.admin.plural', locale: 'bg'))->toBe('Администратори')
        ->and(Translations::get('users.two_factor.email_send_failed', locale: 'bg'))->toBe('Имейлът не можа да бъде изпратен автоматично.')
        ->and(Translations::get('audit_logs.event_types.login_success', locale: 'bg'))->toBe('Успешен вход')
        ->and(Translations::get('common.table.show_columns', locale: 'bg'))->toBe('Покажи колони')
        ->and(Translations::get('common.confirm', locale: 'bg'))->toBe('Потвърди')
        ->and(Translations::get('common.confirm_delete', locale: 'bg'))->toBe('Изтрий');
});

test('user translations are shared on inertia pages', function () {
    get(route('home'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('translations.users.admin.create_title', 'New administrator')
            ->where('translations.users.tabs.profile', 'Profile'));
});

test('authenticated dashboard uses english navigation labels when locale is en', function () {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    actingAs($profiler)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('locale', 'en')
            ->where('translations.nav.logs', 'Logs')
            ->where('translations.common.dashboard', 'Dashboard')
            ->where('auth.user.role_label', 'Profiler'));
});

test('authenticated audit logs page uses english labels when locale is en', function () {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    actingAs($profiler)
        ->get(route('audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('locale', 'en')
            ->where('translations.audit_logs.title', 'Audit')
            ->where('translations.nav.logs', 'Logs')
            ->where('translations.common.table.columns', 'Columns')
            ->where('translations.common.table.rows_per_page', 'Rows per page'));
});

test('authenticated audit logs page uses bulgarian labels when locale is bg', function () {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    actingAs($profiler)
        ->withSession(['locale' => 'bg'])
        ->get(route('audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('locale', 'bg')
            ->where('translations.audit_logs.title', 'Одит')
            ->where('translations.nav.logs', 'Журнали')
            ->where('translations.common.table.columns', 'Колони')
            ->where('translations.common.table.page_of', 'Страница :current от :total'));
});

test('authenticated dashboard uses bulgarian navigation labels when locale is bg', function () {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    actingAs($profiler)
        ->withSession(['locale' => 'bg'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('locale', 'bg')
            ->where('translations.nav.users', 'Потребители')
            ->where('translations.nav.logs', 'Журнали')
            ->where('translations.common.dashboard', 'Табло')
            ->where('auth.user.role_label', 'Профайлер'));
});

test('user management navigation is available only for profiler and admin roles', function () {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    actingAs($profiler)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('auth.user.can_manage_users', true)
            ->where('auth.user.is_profiler', true));

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('auth.user.can_manage_users', true)
            ->where('auth.user.is_admin', true));

    $officeUser = User::factory()->create();
    $officeUser->assignRole('user');

    actingAs($officeUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('auth.user.can_manage_users', false)
            ->where('auth.user.has_office_access', true));
});
