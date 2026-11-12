<?php

use App\Models\Access;
use App\Models\CalendarEvent;
use App\Models\Document;
use App\Models\Group;
use App\Models\ProfitEntry;
use App\Models\ProfitType;
use App\Models\Project;
use App\Models\ProjectEmailAttachment;
use App\Models\ProjectEmailLink;
use App\Models\ProjectGitRepository;
use App\Models\ProjectRevision;
use App\Models\ProjectTodo;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    foreach (['profiler', 'admin', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('group CRUD is forbidden to non-admin roles including direct writes', function (string $role): void {
    $actor = User::factory()->create();
    $actor->assignRole($role);
    $group = Group::factory()->create();

    $this->actingAs($actor)->get(route('groups.index'))->assertForbidden();
    $this->postJson(route('groups.store'), ['name' => 'Forged'])->assertForbidden();
    $this->putJson(route('groups.update', $group), ['name' => 'Forged'])->assertForbidden();
    $this->deleteJson(route('groups.destroy', $group))->assertForbidden();

    expect($actor->can('viewAny', Group::class))->toBeFalse()
        ->and($actor->can('create', Group::class))->toBeFalse()
        ->and($actor->can('update', $group))->toBeFalse()
        ->and($actor->can('delete', $group))->toBeFalse()
        ->and($group->fresh()->name)->toBe($group->name);
})->with(['profiler', 'user']);

test('guests cannot manage groups', function (): void {
    $this->get(route('groups.index'))->assertRedirect(route('login'));
    $this->postJson(route('groups.store'), ['name' => 'Guest'])->assertUnauthorized();
});

test('admin creates renames lists and deletes an empty group without exposing key material', function (): void {
    Log::spy();
    $this->actingAs($this->admin)->post(route('groups.store'), [
        'name' => 'Team', 'id' => 999999, 'access_encryption_key' => 'forged-secret',
        'encryption_settings' => ['key' => 'forged-secret'],
    ])->assertSessionHasNoErrors()->assertRedirect(route('groups.index'));

    $group = Group::query()->sole();
    $wrapped = $group->getRawOriginal('access_encryption_key');
    $encoded = Crypt::decryptString($wrapped);
    expect(strlen(base64_decode($encoded, true)))->toBe(32)
        ->and($group->id)->not->toBe(999999)
        ->and(User::query()->count())->toBe(1)
        ->and(Project::query()->count())->toBe(0)
        ->and(ProfitType::query()->count())->toBe(0);

    $response = $this->get(route('groups.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('groups/Index')->has('groups', 1)
        ->where('groups.0', ['id' => $group->id, 'name' => 'Team', 'users_count' => 0]));
    foreach ([$wrapped, $encoded, 'forged-secret'] as $secret) {
        expect($response->getContent())->not->toContain($secret);
    }

    $this->put(route('groups.update', $group), ['name' => 'Renamed', 'id' => 999999, 'access_encryption_key' => 'replaced'])
        ->assertSessionHasNoErrors()->assertRedirect(route('groups.index'));
    expect($group->fresh()->name)->toBe('Renamed')
        ->and($group->fresh()->getRawOriginal('access_encryption_key'))->toBe($wrapped);

    $this->delete(route('groups.destroy', $group))->assertSessionHasNoErrors()->assertRedirect(route('groups.index'));
    expect($group->fresh())->toBeNull();
    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('warning');
    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('debug');
});

test('group names are validated without flashing forged keys', function (mixed $name): void {
    $this->actingAs($this->admin)->from(route('groups.index'))->post(route('groups.store'), [
        'name' => $name, 'access_encryption_key' => 'do-not-flash',
    ])->assertSessionHasErrors('name')->assertSessionMissing('_old_input.access_encryption_key');
    expect(Group::query()->count())->toBe(0);
})->with([null, '', '   ', 'array input' => [['array']], str_repeat('a', 256)]);

test('missing or invalid generated key material rolls creation back with a safe error', function (?string $material): void {
    Log::spy();
    $encrypter = app('encrypter');
    Crypt::swap(Mockery::mock($encrypter)->makePartial());
    Crypt::shouldReceive('encryptString')->once()->andReturnUsing(function () use ($material, $encrypter): string {
        if ($material === null) {
            throw new RuntimeException('sensitive internal encryption configuration');
        }

        return $encrypter->encryptString($material);
    });
    Crypt::shouldReceive('decryptString')->andReturnUsing(fn (string $value): string => $encrypter->decryptString($value));

    $response = $this->actingAs($this->admin)->postJson(route('groups.store'), ['name' => 'No key'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    expect(Group::query()->count())->toBe(0)
        ->and($response->getContent())->not->toContain('sensitive internal encryption configuration');
    Log::shouldNotHaveReceived('error');
})->with([null, '', 'invalid', base64_encode('short')]);

test('group deletion is blocked by assigned users', function (): void {
    $user = User::factory()->officeUser()->create();
    $group = $user->group;
    $this->actingAs($this->admin)->get(route('groups.index'))->assertInertia(fn (Assert $page) => $page->where('groups.0.users_count', 1));
    $this->deleteJson(route('groups.destroy', $group))->assertUnprocessable()->assertJsonValidationErrors('group');
    expect($user->fresh()->group_id)->toBe($group->id)->and($group->fresh())->not->toBeNull();
});

test('group data and project subresources survive rejected group deletion after all members leave', function (string $modelClass): void {
    $record = $modelClass::factory()->create();
    $group = Group::query()->findOrFail($record->group_id ?? $record->project->group_id);
    $key = $group->getRawOriginal('access_encryption_key');
    foreach ($group->users()->get() as $member) {
        $member->group()->associate(Group::factory()->create());
        $member->save();
    }

    $this->actingAs($this->admin)->putJson(route('groups.update', $group), ['name' => 'Renamed'])
        ->assertRedirect(route('groups.index'));
    $this->deleteJson(route('groups.destroy', $group))->assertUnprocessable()->assertJsonValidationErrors('group');
    expect($group->fresh()->getRawOriginal('access_encryption_key'))->toBe($key)
        ->and($record->fresh())->not->toBeNull();
    if ($record->group_id !== null) {
        expect($record->fresh()->group_id)->toBe($group->id);
    }
})->with([Access::class, CalendarEvent::class, Document::class, ProfitEntry::class, ProfitType::class, Project::class, Task::class, ProjectEmailLink::class, ProjectEmailAttachment::class, ProjectRevision::class, ProjectTodo::class, ProjectGitRepository::class]);

test('late foreign key failure during group deletion becomes a controlled error', function (): void {
    $group = Group::factory()->create();
    Group::deleting(function (): void {
        throw new QueryException('sqlite', 'delete from groups where id = ?', [1], new PDOException('FOREIGN KEY constraint failed', 23000));
    });

    try {
        $this->actingAs($this->admin)->deleteJson(route('groups.destroy', $group))
            ->assertUnprocessable()->assertJsonValidationErrors('group');
        expect($group->fresh())->not->toBeNull();
    } finally {
        Group::flushEventListeners();
        Group::clearBootedModels();
    }
});

test('admin group permissions do not grant module or Access decryption access', function (): void {
    $record = Access::factory()->encrypted()->create();
    $this->actingAs($this->admin)->get(route('groups.index'))->assertOk();
    foreach (['projects.index', 'documents.index', 'calendar.index', 'accesses.index', 'profits.index', 'internal.accesses.index'] as $route) {
        $this->getJson(route($route))->assertForbidden();
    }
    $this->postJson(route('internal.accesses.transform-data'), ['access_ids' => [$record->id], 'operation' => 'decrypt'])
        ->assertForbidden();
    $this->putJson(route('access-encryption-key.rotate'), ['key' => 'forged'])->assertForbidden();
    expect($record->fresh()->is_encrypted)->toBeTrue();
});
