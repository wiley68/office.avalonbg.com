<?php

use App\Models\Access;
use App\Models\CalendarEvent;
use App\Models\Document;
use App\Models\Group;
use App\Models\ProfitEntry;
use App\Models\Project;
use App\Models\ProjectEmailLink;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    foreach (['profiler', 'admin', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->group = Group::factory()->create();
    $this->userData = [
        'name' => 'Managed account', 'email' => 'managed@example.test',
        'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        'status' => 1, 'group_id' => $this->group->id,
    ];
});

test('user creation requires an existing single group and forged roles cannot elevate it', function (): void {
    $this->actingAs($this->admin)->postJson(route('users.store'), [
        ...$this->userData, 'role' => 'profiler', 'roles' => ['admin', 'profiler'],
        'groups' => [999], 'user_id' => $this->admin->id,
    ])->assertRedirect(route('users.index'));
    $user = User::query()->where('email', $this->userData['email'])->sole();
    expect($user->getRoleNames()->all())->toBe(['user'])
        ->and($user->group_id)->toBe($this->group->id)
        ->and($user->must_change_password)->toBeTrue();
});

test('invalid group input fails for creation and updates including status changes', function (mixed $groupId): void {
    $user = User::factory()->officeUser($this->group)->create();
    $data = [...$this->userData, 'group_id' => $groupId];
    $this->actingAs($this->admin)->postJson(route('users.store'), $data)
        ->assertUnprocessable()->assertJsonValidationErrors('group_id');
    $this->putJson(route('users.update', $user), [...$data, 'email' => $user->email, 'status' => 0])
        ->assertUnprocessable()->assertJsonValidationErrors('group_id');
    expect($user->fresh()->group_id)->toBe($this->group->id)
        ->and($user->fresh()->status)->toBe(1)
        ->and(User::query()->where('email', $this->userData['email'])->exists())->toBeFalse();
})->with([null, '', 999999, 0, 'invalid', 'multiple groups' => [[1, 2]]]);

test('missing and deleted group ids are rejected', function (): void {
    $user = User::factory()->officeUser($this->group)->create();
    $deleted = Group::factory()->create();
    $deletedId = $deleted->id;
    $deleted->delete();
    $data = $this->userData;
    unset($data['group_id']);
    $this->actingAs($this->admin)->postJson(route('users.store'), $data)->assertJsonValidationErrors('group_id');
    $this->putJson(route('users.update', $user), [...$data, 'email' => $user->email])->assertJsonValidationErrors('group_id');
    $this->postJson(route('users.store'), [...$data, 'group_id' => $deletedId])->assertJsonValidationErrors('group_id');
    $this->putJson(route('users.update', $user), [...$data, 'email' => $user->email, 'group_id' => $deletedId])->assertJsonValidationErrors('group_id');
});

test('profiler creates only admins and cannot assign privileged accounts to a group', function (): void {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');
    $this->actingAs($profiler)->postJson(route('users.store'), $this->userData)
        ->assertUnprocessable()->assertJsonValidationErrors('group_id');
    $data = $this->userData;
    unset($data['group_id']);
    $this->postJson(route('users.store'), [...$data, 'role' => 'profiler', 'roles' => ['profiler']])
        ->assertRedirect(route('users.index'));
    $admin = User::query()->where('email', $data['email'])->sole();
    expect($admin->getRoleNames()->all())->toBe(['admin'])->and($admin->group_id)->toBeNull();
    $this->putJson(route('users.update', $admin), $this->userData)->assertJsonValidationErrors('group_id');
    $this->putJson(route('users.update', $admin), [...$data, 'role' => 'profiler', 'roles' => ['profiler']])
        ->assertRedirect(route('users.edit', $admin));
    expect($admin->fresh()->getRoleNames()->all())->toBe(['admin'])->and($admin->fresh()->group_id)->toBeNull();
});

test('higher roles cannot be edited deleted or promoted through forged fields', function (string $actorRole, string $targetRole): void {
    $actor = User::factory()->create();
    $actor->assignRole($actorRole);
    $target = User::factory()->create();
    $target->assignRole($targetRole);
    $this->actingAs($actor)->get(route('users.edit', $target))->assertForbidden();
    $this->putJson(route('users.update', $target), [...$this->userData, 'role' => 'user'])->assertForbidden();
    $this->deleteJson(route('users.destroy', $target))->assertForbidden();
    expect($target->fresh()->getRoleNames()->all())->toBe([$targetRole]);
})->with([['admin', 'admin'], ['admin', 'profiler'], ['profiler', 'profiler'], ['profiler', 'user']]);

test('admin forms and user listing expose only group identifiers names and membership', function (): void {
    $user = User::factory()->officeUser($this->group)->create();
    $this->actingAs($this->admin)->get(route('users.create'))->assertInertia(fn (Assert $page) => $page
        ->where('groups.0', ['id' => $this->group->id, 'name' => $this->group->name]));
    $this->get(route('users.edit', $user))->assertInertia(fn (Assert $page) => $page
        ->where('user.group_id', $this->group->id)->missing('groups.0.access_encryption_key'));
    $this->getJson(route('internal.users.index'))->assertJsonPath('data.0.group', ['id' => $this->group->id, 'name' => $this->group->name]);
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');
    $this->actingAs($profiler)->get(route('users.create'))->assertInertia(fn (Assert $page) => $page->has('groups', 0));
});

test('reassignment invalidates all user sessions and remember tokens while records retain ownership and creator', function (): void {
    $user = User::factory()->officeUser($this->group)->create();
    $project = Project::factory()->for($user)->create();
    $access = Access::factory()->for($user)->create();
    $target = Group::factory()->create();
    $loginKey = Auth::guard('web')->getName();
    $sessionId = str_repeat('a', 40);
    foreach ([$sessionId, str_repeat('b', 40)] as $id) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => base64_encode(json_encode([$loginKey => $user->id])), 'last_activity' => time()]);
    }
    DB::table('sessions')->insert(['id' => str_repeat('c', 40), 'user_id' => $this->admin->id, 'payload' => base64_encode('{}'), 'last_activity' => time()]);

    config(['session.driver' => 'database']);
    Auth::forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    $this->withCookie(config('session.cookie'), $sessionId)->get(route('dashboard'))->assertOk();
    $this->assertAuthenticatedAs($user);

    config(['session.driver' => 'array']);
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    $this->actingAs($this->admin)->putJson(route('users.update', $user), [
        ...$this->userData, 'email' => $user->email, 'group_id' => $target->id,
        'role' => 'admin', 'roles' => ['profiler'],
    ])->assertRedirect(route('users.edit', $user));

    expect($user->fresh()->group_id)->toBe($target->id)
        ->and($user->fresh()->getRoleNames()->all())->toBe(['user'])
        ->and($user->fresh()->remember_token)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', str_repeat('c', 40))->exists())->toBeTrue();
    foreach ([$project, $access] as $record) {
        expect($record->fresh()->group_id)->toBe($this->group->id)->and($record->fresh()->user_id)->toBe($user->id);
    }

    config(['session.driver' => 'database']);
    Auth::forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    $this->withCookie(config('session.cookie'), $sessionId)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('unchanged group keeps existing sessions and remember token', function (): void {
    $user = User::factory()->officeUser($this->group)->create();
    DB::table('sessions')->insert(['id' => 'unchanged', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $this->actingAs($this->admin)->putJson(route('users.update', $user), [...$this->userData, 'email' => $user->email])
        ->assertRedirect(route('users.edit', $user));
    expect(DB::table('sessions')->where('id', 'unchanged')->exists())->toBeTrue()
        ->and($user->fresh()->remember_token)->toBe($user->remember_token);
});

test('profile updates cannot change group role or status and registration is unavailable', function (): void {
    $user = User::factory()->officeUser($this->group)->create();
    $this->actingAs($user)->patchJson(route('profile.update'), [
        'name' => $user->name, 'email' => $user->email, 'group_id' => Group::factory()->create()->id,
        'role' => 'admin', 'roles' => ['profiler'], 'status' => 0,
    ])->assertRedirect(route('profile.edit'));
    expect($user->fresh()->group_id)->toBe($this->group->id)
        ->and($user->fresh()->getRoleNames()->all())->toBe(['user'])->and($user->fresh()->status)->toBe(1);
    $this->postJson('/register', $this->userData)->assertNotFound();
});

test('missing group fails closed on every module route including internal APIs and settings', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $this->actingAs($user);
    foreach (['projects.index', 'documents.index', 'calendar.index', 'accesses.index', 'profits.index', 'internal.projects.index', 'internal.documents.index', 'internal.calendar-events.index', 'internal.accesses.index', 'internal.profits.index', 'internal.profit-types.index', 'internal.imap.folders.index', 'email.edit'] as $route) {
        $this->getJson(route($route))->assertForbidden();
    }
    foreach (['projects.store', 'documents.store', 'calendar-events.store', 'accesses.store', 'profits.store', 'profit-types.store', 'internal.documents.store', 'internal.accesses.transform-data'] as $route) {
        $this->postJson(route($route), [])->assertForbidden();
    }
    $this->get(route('profile.edit'))->assertOk();
});

test('valid group permits module entry without changing query ownership', function (): void {
    $user = User::factory()->officeUser($this->group)->create();
    $this->actingAs($user);
    foreach (['projects.index', 'documents.index', 'calendar.index', 'accesses.index', 'profits.index'] as $route) {
        $this->get(route($route))->assertOk();
    }
});

test('creator cannot be deleted through admin or profile but can be reassigned and deactivated', function (string $modelClass): void {
    if (in_array($modelClass, [Task::class, ProjectEmailLink::class], true)) {
        $project = Project::factory()->create();
        $creator = User::factory()->officeUser($project->group)->create();
        $record = $modelClass::factory()->for($project)->for($creator)->create();
    } else {
        $record = $modelClass::factory()->create();
        $creator = $record->user;
    }
    $oldGroup = $record->group_id;
    $this->actingAs($this->admin)->deleteJson(route('users.destroy', $creator))
        ->assertUnprocessable()->assertJsonValidationErrors('user');
    $this->actingAs($creator)->deleteJson(route('profile.destroy'), ['password' => 'Password123!'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->assertAuthenticatedAs($creator);
    $target = Group::factory()->create();
    $this->actingAs($this->admin)->putJson(route('users.update', $creator), [
        'name' => $creator->name, 'email' => $creator->email, 'status' => 0, 'group_id' => $target->id,
    ])->assertRedirect(route('users.edit', $creator));
    expect($creator->fresh()->status)->toBe(0)->and($creator->fresh()->group_id)->toBe($target->id)
        ->and($record->fresh()->group_id)->toBe($oldGroup)->and($record->fresh()->user_id)->toBe($creator->id);
})->with([Project::class, Document::class, CalendarEvent::class, Access::class, ProfitEntry::class, Task::class, ProjectEmailLink::class]);

test('late creator FK failures are controlled and preserve profile authentication', function (): void {
    $user = User::factory()->officeUser($this->group)->create();
    User::deleting(function (): void {
        throw new QueryException('sqlite', 'delete from users where id = ?', [1], new PDOException('FOREIGN KEY constraint failed', 23000));
    });
    try {
        $this->actingAs($this->admin)->deleteJson(route('users.destroy', $user))->assertJsonValidationErrors('user');
        $this->actingAs($user)->deleteJson(route('profile.destroy'), ['password' => 'Password123!'])->assertJsonValidationErrors('password');
        $this->assertAuthenticatedAs($user);
        expect($user->fresh())->not->toBeNull();
    } finally {
        User::flushEventListeners();
        User::clearBootedModels();
    }
});

test('role assignment failure rolls account creation back', function (): void {
    Role::query()->where('name', 'user')->delete();
    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($this->admin)->postJson(route('users.store'), $this->userData))
        ->toThrow(RoleDoesNotExist::class);
    expect(User::query()->where('email', $this->userData['email'])->exists())->toBeFalse();
});
