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
use Database\Seeders\ProfitTypeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (! app()->environment('testing')
        || config('database.default') !== 'sqlite'
        || config('database.connections.sqlite.database') !== ':memory:'
        || filled(config('database.connections.sqlite.url'))
        || ! config('database.connections.sqlite.foreign_key_constraints')) {
        throw new RuntimeException('Foundation tests require isolated SQLite memory storage with foreign keys.');
    }

    expect(Schema::getConnection()->selectOne('PRAGMA database_list')->file)->toBe('');
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
});

test('group keys are generated independently wrapped and hidden on every creation path', function (): void {
    $third = new Group(['name' => 'Third']);
    $third->forceFill(['access_encryption_key' => 'invalid'])->save();
    $groups = [
        Group::query()->create(['name' => 'First']),
        Group::factory()->create(),
        $third,
    ];

    $keys = [];

    foreach ($groups as $group) {
        $wrappedKey = $group->fresh()->getRawOriginal('access_encryption_key');
        $encodedKey = Crypt::decryptString($wrappedKey);
        $key = base64_decode($encodedKey, true);

        expect(is_string($key) && strlen($key) === 32)->toBeTrue()
            ->and($wrappedKey !== $encodedKey)->toBeTrue()
            ->and(array_key_exists('access_encryption_key', $group->toArray()))->toBeFalse()
            ->and(str_contains($group->toJson(), $encodedKey))->toBeFalse()
            ->and($group->isFillable('access_encryption_key'))->toBeFalse();

        $keys[] = $key;
    }

    expect(count(array_unique($keys)) === 3)->toBeTrue();
    expect(fn () => Group::withoutEvents(fn () => Group::query()->create(['name' => 'Missing key'])))
        ->toThrow(QueryException::class);
});

test('group migration precedes users and ownership columns and indexes exist', function (): void {
    $migrations = collect(glob(database_path('migrations/*.php')))->map(basename(...))->sort()->values();

    expect($migrations->search('0001_01_01_000000_create_groups_table.php'))
        ->toBeLessThan($migrations->search('0001_01_01_000000_create_users_table.php'));

    foreach (['users', 'projects', 'documents', 'calendar_events', 'accesses', 'profit_entries', 'profit_types', 'tasks', 'project_email_links', 'project_email_attachments', 'document_project', 'document_task'] as $table) {
        expect(Schema::hasColumn($table, 'group_id'))->toBeTrue();
        $column = collect(Schema::getColumns($table))->firstWhere('name', 'group_id');
        expect($column['nullable'])->toBe($table === 'users');
    }

    foreach (['project_revisions', 'project_todos', 'project_git_repositories'] as $table) {
        expect(Schema::hasColumn($table, 'group_id'))->toBeFalse();
    }

    foreach (['projects', 'documents', 'calendar_events', 'accesses', 'profit_entries', 'tasks', 'project_email_links'] as $table) {
        $creatorForeignKey = collect(Schema::getForeignKeys($table))->first(fn (array $key): bool => $key['columns'] === ['user_id']);
        expect(strtolower($creatorForeignKey['on_delete']))->toBe('restrict');
    }

    foreach (['calendar_events' => ['group_id', 'starts_at'], 'profit_entries' => ['group_id', 'date'], 'profit_types' => ['group_id', 'name', 'kind']] as $table => $columns) {
        expect(collect(Schema::getIndexes($table))->contains(fn (array $index): bool => $index['columns'] === $columns))->toBeTrue();
    }
});

test('primary factories default to a grouped creator and prevent ownership mass assignment', function (string $modelClass): void {
    $record = $modelClass::factory()->create();

    expect($record->group_id)->toBe($record->user->group_id)
        ->and($record->user->hasRole('user'))->toBeTrue()
        ->and($record->group->exists)->toBeTrue()
        ->and($record->isFillable('group_id'))->toBeFalse();
})->with([Access::class, Project::class, Document::class, CalendarEvent::class, ProfitEntry::class]);

test('user creator relationships remain distinct from group ownership after reassignment', function (): void {
    $creator = User::factory()->officeUser()->create();
    $peer = User::factory()->officeUser($creator->group)->create();
    $project = Project::factory()->for($creator)->create();
    $oldGroup = $project->group;

    expect($peer->projects()->count())->toBe(0)
        ->and($peer->group->projects()->count())->toBe(1)
        ->and($creator->isFillable('group_id'))->toBeFalse();

    $creator->group()->associate(Group::factory()->create());
    $creator->save();

    expect($creator->projects()->count())->toBe(1)
        ->and($creator->group->projects()->count())->toBe(0)
        ->and($project->fresh()->group_id)->toBe($oldGroup->id)
        ->and($project->fresh()->user_id)->toBe($creator->id);
});

test('creator attribution is immutable and creator deletion is restricted', function (string $modelClass): void {
    $record = $modelClass::factory()->create();
    $creator = $record->user;
    $peer = User::factory()->officeUser($creator->group)->create();

    expect(fn () => $record->update(['user_id' => $peer->id]))->toThrow(LogicException::class);
    expect(fn () => $creator->delete())->toThrow(QueryException::class);

    $creator->update(['status' => 0]);
    expect($record->fresh()->user_id)->toBe($creator->id)
        ->and($modelClass::query()->whereKey($record->id)->exists())->toBeTrue();
})->with([Access::class, Project::class, Document::class, CalendarEvent::class, ProfitEntry::class, Task::class, ProjectEmailLink::class]);

test('dependent factories share project ownership and attachments stay in that group', function (): void {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();
    $link = ProjectEmailLink::factory()->for($project)->archived()->create();
    $attachment = ProjectEmailAttachment::factory()->for($link, 'emailLink')->create();

    foreach ([$task, $link, $attachment, $attachment->document] as $record) {
        expect($record->group_id)->toBe($project->group_id);
    }

    expect($task->user_id)->toBe($project->user_id)
        ->and($link->user_id)->toBe($project->user_id);

    foreach ([ProjectRevision::class, ProjectTodo::class, ProjectGitRepository::class] as $modelClass) {
        $record = $modelClass::factory()->for($project)->create();
        expect($record->project->group_id)->toBe($project->group_id);
    }
});

test('a second group member can be the creator of a project dependent record', function (): void {
    $project = Project::factory()->create();
    $creator = User::factory()->officeUser($project->group)->create();
    $task = Task::factory()->for($project)->for($creator)->create();

    expect($task->user_id)->toBe($creator->id)
        ->and($task->group_id)->toBe($project->group_id);
});

test('task parents and revisions from another project are rejected', function (string $relation): void {
    $project = Project::factory()->create();
    $other = Project::factory()->create();
    $foreign = $relation === 'parent_id'
        ? Task::factory()->for($other)->create()
        : ProjectRevision::factory()->for($other)->create();

    expect(fn () => Task::factory()->for($project)->create([$relation => $foreign->id]))
        ->toThrow(LogicException::class);
})->with(['parent_id', 'project_revision_id']);

test('document pivots derive their group from either relationship direction', function (): void {
    $creator = User::factory()->officeUser()->create();
    $peer = User::factory()->officeUser($creator->group)->create();
    $project = Project::factory()->for($creator)->create();
    $task = Task::factory()->for($project)->create();
    $document = Document::factory()->for($peer)->create();

    $project->documents()->syncWithoutDetaching([$document->id]);
    $document->tasks()->syncWithoutDetaching([$task->id]);

    expect($project->documents()->count())->toBe(1)
        ->and($task->documents()->count())->toBe(1);
    $this->assertDatabaseHas('document_project', ['document_id' => $document->id, 'project_id' => $project->id, 'group_id' => $project->group_id]);
    $this->assertDatabaseHas('document_task', ['document_id' => $document->id, 'task_id' => $task->id, 'group_id' => $task->group_id]);

    $document->projects()->detach($project->id);
    $task->documents()->detach($document->id);
    expect($document->projects()->count())->toBe(0)
        ->and($task->documents()->count())->toBe(0);
});

test('foreign group documents cannot be attached to projects tasks or archived email', function (string $target): void {
    $project = Project::factory()->create();
    $document = Document::factory()->create();

    expect(function () use ($target, $project, $document): void {
        match ($target) {
            'project' => $project->documents()->attach($document->id),
            'task' => Task::factory()->for($project)->create()->documents()->attach($document->id),
            'email' => ProjectEmailAttachment::factory()
                ->for(ProjectEmailLink::factory()->for($project), 'emailLink')
                ->for($document)->create(),
        };
    })->toThrow(QueryException::class);
})->with(['project', 'task', 'email']);

test('profit types have group scoped uniqueness and entries cannot use foreign types', function (): void {
    $entry = ProfitEntry::factory()->create();
    $type = $entry->profitType;
    $otherGroup = Group::factory()->create();

    ProfitType::factory()->for($otherGroup)->create(['name' => $type->name, 'kind' => $type->kind]);
    expect(ProfitType::query()->count())->toBe(2);
    expect(fn () => ProfitType::factory()->for($type->group)->create(['name' => $type->name, 'kind' => $type->kind]))
        ->toThrow(QueryException::class);
    expect(fn () => $entry->update(['profit_type_id' => ProfitType::query()->where('group_id', $otherGroup->id)->firstOrFail()->id]))
        ->toThrow(QueryException::class);
});

test('profit seeding is group scoped idempotent and creates no orphan categories', function (): void {
    $this->seed(ProfitTypeSeeder::class);
    expect(ProfitType::query()->count())->toBe(0);

    $groups = Group::factory()->count(2)->create();
    $this->seed(ProfitTypeSeeder::class);
    $this->seed(ProfitTypeSeeder::class);

    expect(ProfitType::query()->count())->toBe(14);
    foreach ($groups as $group) {
        expect($group->profitTypes()->count())->toBe(7);
    }
});

test('groups with members or data cannot be deleted while empty groups can', function (string $kind): void {
    $group = Group::factory()->create();

    match ($kind) {
        'member' => User::factory()->officeUser($group)->create(),
        'type' => ProfitType::factory()->for($group)->create(),
        'data' => tap(Access::factory()->for(User::factory()->officeUser($group))->create(), function (Access $access): void {
            $access->user->group()->associate(Group::factory()->create());
            $access->user->save();
        }),
    };

    expect(fn () => $group->delete())->toThrow(QueryException::class);
    expect(Group::query()->whereKey($group->id)->exists())->toBeTrue();

    $empty = Group::factory()->create();
    $empty->delete();
    expect(Group::query()->whereKey($empty->id)->exists())->toBeFalse();
})->with(['member', 'type', 'data']);

test('factory recycling shares one group across the generated graph', function (): void {
    $group = Group::factory()->create();

    foreach ([Access::class, Project::class, Document::class, CalendarEvent::class, ProfitEntry::class] as $modelClass) {
        $record = $modelClass::factory()->recycle($group)->create();
        expect($record->group_id)->toBe($group->id)
            ->and($record->user->group_id)->toBe($group->id);
    }

    expect(Group::query()->count())->toBe(1);
});

test('composite foreign keys reject a project from another group on dependent updates', function (string $modelClass): void {
    $record = $modelClass::factory()->create();
    $otherProject = Project::factory()->create();

    expect(fn () => $record->update(['project_id' => $otherProject->id]))->toThrow(QueryException::class);
    expect($record->fresh()->project_id)->not->toBe($otherProject->id);
})->with(['task' => [Task::class], 'email' => [ProjectEmailLink::class]]);
