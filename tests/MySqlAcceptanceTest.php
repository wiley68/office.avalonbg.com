<?php

use App\Models\Document;
use App\Models\Group;
use App\Models\ProfitEntry;
use App\Models\ProfitType;
use App\Models\Project;
use App\Models\ProjectEmailAttachment;
use App\Models\ProjectEmailLink;
use App\Models\Task;
use App\Models\User;
use App\Services\ImapMailboxService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProfitTypeSeeder;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\Permission\Models\Role;

/**
 * Opt in: OFFICE_MYSQL_ACCEPTANCE=1 OFFICE_ACCEPTANCE_CREDENTIALS_FILE=/home/wiley/.office-acceptance.cnf
 * vendor/bin/pest tests/MySqlAcceptanceTest.php --bail
 *
 * This file is outside Feature/Unit so the SQLite suite and its guard stay unchanged.
 */
test('ACC-01B isolated MySQL migration bootstrap and integrity acceptance', function (): void {
    if (getenv('OFFICE_MYSQL_ACCEPTANCE') !== '1') {
        $this->markTestSkipped('Explicit OFFICE_MYSQL_ACCEPTANCE=1 is required.');
    }

    $stage = 'credentials';
    $connection = null;
    $transactionStarted = false;
    $writesAllowed = false;
    $check = function (bool $condition, string $label): void {
        expect($condition, $label)->toBeTrue();
    };

    try {
        $file = getenv('OFFICE_ACCEPTANCE_CREDENTIALS_FILE');
        $check(is_string($file) && $file !== '' && is_file($file) && ! is_link($file), 'A dedicated private credentials file is required.');
        $path = realpath($file);
        $check($path === '/home/wiley/.office-acceptance.cnf', 'Only the owner-provided acceptance credentials file is allowed.');
        $check($path !== false && ! str_starts_with($path, dirname(__DIR__).DIRECTORY_SEPARATOR), 'Credentials must be outside the repository.');
        $check((fileperms($path) & 0077) === 0 && is_readable($path), 'Credentials must not be readable by group or others.');
        $credentials = @parse_ini_file($path, true, INI_SCANNER_RAW)['client'] ?? null;
        $check(is_array($credentials), 'A client section is required.');
        $check(($credentials['user'] ?? null) === 'office_acc_test', 'Only the dedicated acceptance user is allowed.');
        $check(($credentials['host'] ?? null) === '127.0.0.1', 'Only the dedicated acceptance host is allowed.');
        $check(($credentials['database'] ?? null) === 'office_groups_acceptance', 'Only the hardcoded acceptance database is allowed.');
        $check(($credentials['port'] ?? '3306') === '3306', 'Unexpected acceptance port.');
        $check(is_string($credentials['password'] ?? null) && $credentials['password'] !== '', 'Dedicated credentials are incomplete.');

        $stage = 'read-only preflight';
        $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=office_groups_acceptance;charset=utf8mb4', $credentials['user'], $credentials['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $assertIdentity = static function (PDO $pdo): void {
            $identity = $pdo->query('SELECT DATABASE() AS db, CURRENT_USER() AS account')->fetch(PDO::FETCH_ASSOC);
            if ($identity['db'] !== 'office_groups_acceptance' || $identity['account'] !== 'office_acc_test@127.0.0.1') {
                throw new RuntimeException('Acceptance SQL identity guard refused the connection.');
            }
        };
        $assertIdentity($pdo);
        $check(str_starts_with($pdo->query('SELECT VERSION()')->fetchColumn(), '8.0.'), 'MySQL 8.0 is required.');
        $grants = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
        $usage = false;
        $schemaGrant = false;
        foreach ($grants as $grant) {
            $check(stripos($grant, 'WITH GRANT OPTION') === false, 'Grant delegation is not allowed.');
            if (str_starts_with($grant, 'GRANT USAGE ON *.* TO ')) {
                $usage = true;
            } elseif (str_starts_with($grant, 'GRANT ALL PRIVILEGES ON `office_groups_acceptance`.* TO ')) {
                $schemaGrant = true;
            } else {
                throw new RuntimeException('Unexpected global, role or non-acceptance grant; no writes allowed.');
            }
        }
        $check($usage && $schemaGrant && count($grants) === 2, 'Only USAGE and the exact acceptance schema grant are allowed.');
        $check($pdo->query('SELECT CURRENT_ROLE()')->fetchColumn() === 'NONE', 'Active MySQL roles are not allowed.');
        $check((int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'office_groups_acceptance'")->fetchColumn() === 0, 'Acceptance schema must contain zero tables; never reset it automatically.');
        fwrite(STDOUT, "\nACC-01B preflight: PASS; dedicated account, exact schema, zero tables, scoped grants.\n");

        $stage = 'isolated application bootstrap';
        $cachePath = sys_get_temp_dir().'/office-acc01b-config-'.bin2hex(random_bytes(8)).'.php';
        foreach (['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $cachePath] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
            Env::getRepository()->set($key, $value);
        }
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $app->loadEnvironmentFrom('.env.office-acceptance-disabled');
        $check(! is_file($app->environmentFilePath()) && ! $app->configurationIsCached(), 'Environment files and configuration cache must not be used.');
        $app->afterBootstrapping(LoadConfiguration::class, function () use ($app, $credentials, $assertIdentity, &$writesAllowed): void {
            $app['config']->set([
                'app.env' => 'testing',
                'app.key' => 'base64:'.base64_encode(random_bytes(32)),
                'app.previous_keys' => [],
                'database.default' => 'acceptance',
                'database.connections' => [
                    'acceptance' => [
                        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306,
                        'database' => 'office_groups_acceptance', 'username' => 'office_acc_test',
                        'password' => $credentials['password'], 'url' => null, 'unix_socket' => '',
                        'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
                        'strict' => true, 'engine' => 'InnoDB',
                    ],
                ],
                'session.driver' => 'array', 'session.connection' => 'acceptance',
                'cache.default' => 'array', 'permission.cache.store' => 'array',
                'queue.default' => 'sync', 'queue.failed.driver' => 'null',
                'queue.batching.database' => 'acceptance', 'queue.failed.database' => 'acceptance',
                'mail.default' => 'array', 'logging.default' => 'null',
                'seeding.profiler' => [
                    'name' => 'Acceptance Profiler', 'email' => 'acc01b-profiler@example.test',
                    'password' => bin2hex(random_bytes(24)).'aA1!',
                ],
            ]);
            $app->resolving('db', function (DatabaseManager $manager) use ($assertIdentity, &$writesAllowed): void {
                $connection = $manager->connection('acceptance');
                $connection->beforeExecuting(function (string $query) use ($connection, $assertIdentity, &$writesAllowed): void {
                    $assertIdentity($connection->getPdo());
                    if (! $writesAllowed && ! preg_match('/^\s*(?:SELECT|SHOW|EXPLAIN|DESCRIBE)\b/i', $query)) {
                        throw new RuntimeException('Writes are locked until every isolation guard and fake is active.');
                    }
                    if (preg_match('/^\s*(?:DROP|TRUNCATE|GRANT|REVOKE|USE)\b/i', $query) || preg_match('/^\s*ALTER\b.*\bDROP\b/is', $query)) {
                        throw new RuntimeException('Destructive or connection-changing SQL is prohibited.');
                    }
                });
            });
        });
        $app->make(Kernel::class)->bootstrap();
        $app->setLocale('en');
        Mail::fake();
        Queue::fake();
        Http::preventStrayRequests();
        $app->instance(ImapMailboxService::class, Mockery::mock(ImapMailboxService::class));
        $connection = $app['db']->connection();
        $assertIdentity($connection->getPdo());
        $check(array_keys(config('database.connections')) === ['acceptance'], 'No development or service connection may remain.');
        $check(config('database.default') === 'acceptance' && config('session.driver') === 'array' && config('cache.default') === 'array', 'Isolation configuration is mandatory.');
        $check(! $app->configurationIsCached(), 'Configuration cache must stay disabled.');
        $check((int) $connection->getPdo()->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'office_groups_acceptance'")->fetchColumn() === 0, 'Application bootstrap must not create tables.');
        fwrite(STDOUT, "ACC-01B isolation: PASS; one guarded connection, synthetic key/bootstrap, array cache/sessions, fake mail/queues.\n");

        $writesAllowed = true;
        $stage = 'migrations';
        $kernel = $app->make(Kernel::class);
        $result = $kernel->call('migrate', ['--database' => 'acceptance', '--no-interaction' => true]);
        $check($result === 0, 'Migration chain must succeed without retries.');
        $expectedMigrations = collect(glob(database_path('migrations/*.php')))->map(fn (string $file): string => pathinfo($file, PATHINFO_FILENAME))->sort()->values()->all();
        $check($connection->table('migrations')->orderBy('migration')->pluck('migration')->all() === $expectedMigrations, 'The complete migration ledger must match the source chain.');
        $tables = collect(Schema::getTables())->pluck('name')->sort()->values()->all();
        $expectedTables = ['accesses', 'audit_logs', 'cache', 'cache_locks', 'calendar_events', 'document_project', 'document_task', 'documents', 'failed_jobs', 'groups', 'job_batches', 'jobs', 'migrations', 'model_has_permissions', 'model_has_roles', 'password_reset_tokens', 'permissions', 'personal_access_tokens', 'profit_entries', 'profit_types', 'project_email_attachments', 'project_email_links', 'project_git_repositories', 'project_revisions', 'project_todos', 'projects', 'role_has_permissions', 'roles', 'sessions', 'tasks', 'user_imap_accounts', 'users'];
        sort($expectedTables);
        $check($tables === $expectedTables, 'All expected application tables must exist.');
        $engines = $connection->select("SELECT ENGINE FROM information_schema.tables WHERE table_schema = 'office_groups_acceptance'");
        $check(count($engines) === count($expectedTables) && collect($engines)->every(fn (object $table): bool => $table->ENGINE === 'InnoDB'), 'All acceptance tables must use InnoDB.');
        fwrite(STDOUT, 'ACC-01B migrations: PASS; '.count($expectedMigrations).' migrations, '.count($tables)." InnoDB tables.\n");

        $stage = 'foreign keys and indexes';
        foreach (['users', 'accesses', 'projects', 'documents', 'calendar_events', 'tasks', 'project_email_links', 'project_email_attachments', 'document_project', 'document_task', 'profit_types', 'profit_entries'] as $table) {
            $column = collect(Schema::getColumns($table))->firstWhere('name', 'group_id');
            $key = collect(Schema::getForeignKeys($table))->first(fn (array $key): bool => $key['columns'] === ['group_id']);
            $check($column !== null && $column['nullable'] === ($table === 'users'), $table.' group nullability');
            $check($key !== null && $key['foreign_table'] === 'groups' && $key['foreign_columns'] === ['id'] && strtolower($key['on_delete']) === 'restrict', $table.' group restriction');
        }
        foreach (['accesses', 'projects', 'documents', 'calendar_events', 'tasks', 'project_email_links', 'profit_entries'] as $table) {
            $key = collect(Schema::getForeignKeys($table))->first(fn (array $key): bool => $key['columns'] === ['user_id']);
            $check($key !== null && $key['foreign_table'] === 'users' && strtolower($key['on_delete']) === 'restrict', $table.' creator restriction');
        }
        foreach ([
            ['tasks', ['project_id', 'group_id'], 'projects', 'cascade'],
            ['project_email_links', ['project_id', 'group_id'], 'projects', 'cascade'],
            ['document_project', ['document_id', 'group_id'], 'documents', 'cascade'],
            ['document_project', ['project_id', 'group_id'], 'projects', 'cascade'],
            ['document_task', ['document_id', 'group_id'], 'documents', 'cascade'],
            ['document_task', ['task_id', 'group_id'], 'tasks', 'cascade'],
            ['project_email_attachments', ['project_email_link_id', 'group_id'], 'project_email_links', 'cascade'],
            ['project_email_attachments', ['document_id', 'group_id'], 'documents', 'restrict'],
            ['profit_entries', ['profit_type_id', 'group_id'], 'profit_types', 'restrict'],
        ] as [$table, $columns, $parent, $onDelete]) {
            $key = collect(Schema::getForeignKeys($table))->first(fn (array $key): bool => $key['columns'] === $columns);
            $check($key !== null && $key['foreign_table'] === $parent && $key['foreign_columns'] === ['id', 'group_id'] && strtolower($key['on_delete']) === $onDelete, $table.' composite integrity');
            $check(collect(Schema::getIndexes($parent))->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['id', 'group_id']), $parent.' composite parent index');
        }
        $emailIndexes = collect(Schema::getIndexes('project_email_links'));
        $identity = $emailIndexes->firstWhere('name', 'email_links_source_message_unique');
        $check($identity !== null && $identity['unique'] && $identity['columns'] === ['project_id', 'source_mailbox', 'folder', 'uidvalidity', 'imap_uid'], 'Complete email identity unique index');
        $check(! $emailIndexes->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['project_id', 'folder', 'imap_uid']), 'Obsolete email identity unique must not exist.');
        $check(collect(Schema::getIndexes('profit_types'))->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['group_id', 'name', 'kind']), 'Group-scoped profit category uniqueness');
        fwrite(STDOUT, "ACC-01B FK/index metadata: PASS.\n");

        $stage = 'bootstrap validation';
        $bootstrap = config('seeding.profiler');
        foreach ([['name', null], ['email', 'invalid-address'], ['password', null]] as [$field, $invalid]) {
            config(['seeding.profiler' => [...$bootstrap, $field => $invalid]]);
            $rejected = false;
            try {
                (new DatabaseSeeder)->run();
            } catch (ValidationException) {
                $rejected = true;
            }
            $check($rejected && User::query()->count() === 0 && Role::query()->count() === 0 && Group::query()->count() === 0, 'Invalid bootstrap must fail before seed data writes.');
        }
        config(['seeding.profiler' => $bootstrap]);
        (new DatabaseSeeder)->run();
        $check(Role::query()->orderBy('name')->pluck('name')->all() === ['admin', 'profiler', 'user'] && Role::query()->where('guard_name', 'web')->count() === 3, 'Exactly the three web roles are seeded.');
        $profiler = User::query()->sole();
        $check($profiler->isProfiler() && $profiler->group_id === null && ! $profiler->hasEnabledTwoFactorAuthentication(), 'The initial profiler is groupless without 2FA.');
        $check(Group::query()->count() === 0 && ProfitType::query()->count() === 0, 'Bootstrap creates no demo groups or categories.');
        fwrite(STDOUT, "ACC-01B bootstrap: PASS; synthetic profiler and three roles only.\n");

        $stage = 'transactional fixture checks';
        $connection->beginTransaction();
        $transactionStarted = true;
        $profiler->forceFill(['two_factor_secret' => encrypt(bin2hex(random_bytes(16))), 'two_factor_recovery_codes' => encrypt(json_encode(['synthetic-recovery-code'])), 'two_factor_confirmed_at' => now()])->save();
        $attributes = $profiler->getRawOriginal();
        config(['seeding.profiler.password' => bin2hex(random_bytes(24)).'aA1!', 'seeding.profiler.name' => 'Changed synthetic name']);
        (new DatabaseSeeder)->run();
        $check($profiler->fresh()->getRawOriginal() === $attributes, 'Repeated seed must preserve password and all 2FA attributes.');
        $first = Group::factory()->create();
        $second = Group::factory()->create();
        $keyOne = Crypt::decryptString($first->getRawOriginal('access_encryption_key'));
        $keyTwo = Crypt::decryptString($second->getRawOriginal('access_encryption_key'));
        $check(strlen(base64_decode($keyOne, true)) === 32 && strlen(base64_decode($keyTwo, true)) === 32 && $keyOne !== $keyTwo, 'Group keys are independent secure wrapped keys.');
        $check($first->getRawOriginal('access_encryption_key') !== $keyOne && ! array_key_exists('access_encryption_key', $first->toArray()) && ! $first->isFillable('access_encryption_key'), 'Group keys are wrapped and hidden.');
        (new ProfitTypeSeeder)->run();
        (new ProfitTypeSeeder)->run();
        $check($first->profitTypes()->count() === 7 && $second->profitTypes()->count() === 7 && ProfitType::query()->count() === 14, 'Profit categories are group-scoped and idempotent.');
        $creator = User::factory()->officeUser($first)->create();
        $outsider = User::factory()->officeUser($second)->create();
        $project = Project::factory()->for($creator)->create();
        $task = Task::factory()->for($project)->create();
        $document = Document::factory()->for($creator)->create();
        $foreignDocument = Document::factory()->for($outsider)->create();
        $project->documents()->attach($document->id);
        $task->documents()->attach($document->id);
        $check($project->documents()->count() === 1 && $task->documents()->count() === 1, 'Valid same-group document associations work.');
        $reject = function (Closure $operation, int $code, string $label) use ($check): void {
            $rejected = false;
            try {
                $operation();
            } catch (QueryException $exception) {
                $rejected = ($exception->errorInfo[0] ?? null) === '23000' && (int) ($exception->errorInfo[1] ?? 0) === $code;
            }
            $check($rejected, $label);
        };
        $reject(fn () => $first->delete(), 1451, 'A group with members/data cannot be deleted.');
        $reject(fn () => $creator->delete(), 1451, 'An attributed creator cannot be deleted.');
        $reject(fn () => $project->documents()->attach($foreignDocument->id), 1452, 'Cross-group project document association is rejected.');
        $reject(fn () => $task->documents()->attach($foreignDocument->id), 1452, 'Cross-group task document association is rejected.');
        $type = $first->profitTypes()->where('kind', 'income')->firstOrFail();
        $foreignType = $second->profitTypes()->where('kind', 'income')->firstOrFail();
        $entry = ProfitEntry::factory()->for($creator)->for($type, 'profitType')->create();
        $reject(fn () => $entry->update(['profit_type_id' => $foreignType->id]), 1452, 'Cross-group profit reference is rejected.');
        $reject(fn () => $connection->table('profit_entries')->where('id', $entry->id)->update(['profit_type_id' => 0]), 1452, 'A missing profit FK is rejected by MySQL.');
        $reject(fn () => ProfitType::factory()->for($first)->create(['name' => $type->name, 'kind' => $type->kind]), 1062, 'Duplicate category within one group is rejected.');
        $message = ['source_mailbox' => str_repeat('a', 64), 'folder' => 'INBOX', 'uidvalidity' => 100, 'imap_uid' => 42];
        $link = ProjectEmailLink::factory()->for($project)->archived()->create($message);
        ProjectEmailAttachment::factory()->for($link, 'emailLink')->for($document)->create();
        $reject(fn () => ProjectEmailAttachment::factory()->for($link, 'emailLink')->for($foreignDocument)->create(['imap_part' => '3']), 1452, 'Cross-group email document association is rejected.');
        $reject(fn () => ProjectEmailLink::factory()->for($project)->create($message), 1062, 'Duplicate complete email identity is rejected.');
        ProjectEmailLink::factory()->for($project)->create([...$message, 'source_mailbox' => str_repeat('b', 64)]);
        ProjectEmailLink::factory()->for($project)->create([...$message, 'uidvalidity' => 101]);
        $check($project->emailLinks()->count() === 3, 'Different mailbox sources and UIDVALIDITY generations are accepted.');
        fwrite(STDOUT, "ACC-01B seed idempotency, group keys and negative integrity checks: PASS.\n");

        $stage = 'fixture rollback and final inventory';
        $assertIdentity($connection->getPdo());
        $connection->rollBack();
        $transactionStarted = false;
        $check(Group::query()->count() === 0 && User::query()->count() === 1 && ProfitType::query()->count() === 0 && Role::query()->count() === 3, 'Only initial bootstrap records may remain.');
        $check(! $profiler->fresh()->hasEnabledTwoFactorAuthentication(), 'Fixture 2FA changes are rolled back.');
        foreach ($expectedTables as $table) {
            $expectedCount = match ($table) {
                'migrations' => count($expectedMigrations), 'users', 'model_has_roles' => 1, 'roles' => 3, default => 0
            };
            $check($connection->table($table)->count() === $expectedCount, $table.' final exact inventory');
        }
        fwrite(STDOUT, "ACC-01B final inventory: PASS; schema retained, migration ledger, three roles, one synthetic profiler; fixture records rolled back.\n");
    } catch (Throwable $exception) {
        $detail = $exception instanceof PDOException || $exception instanceof QueryException
            ? ' SQLSTATE '.($exception->errorInfo[0] ?? 'unknown').' / driver '.($exception->errorInfo[1] ?? 'unknown')
            : '';
        if ($exception instanceof AssertionFailedError) {
            $detail .= ' '.$exception->getMessage();
        } elseif ($stage === 'migrations' && $exception instanceof QueryException) {
            $detail .= ' '.($exception->errorInfo[2] ?? 'DDL failure').' SQL: '.$exception->getSql();
        } elseif (get_class($exception) === RuntimeException::class) {
            $detail .= ' '.$exception->getMessage();
        } else {
            $detail .= ' '.get_class($exception);
        }
        throw new RuntimeException('ACC-01B stopped at '.$stage.'.'.$detail.' No retry or reset was performed; secret exception values suppressed.');
    } finally {
        if ($transactionStarted && $connection !== null) {
            $assertIdentity($connection->getPdo());
            $connection->rollBack();
        }
        $connection?->disconnect();
        HandleExceptions::flushState($this);
    }
});

test('ACC-01B bootstrap exception handlers are restored', function (): void {
    $errorHandler = get_error_handler();
    $exceptionHandler = get_exception_handler();
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->instance('config', new Repository(['app' => ['env' => 'testing', 'debug' => false]]));
    $app->instance('env', 'testing');
    (new HandleExceptions)->bootstrap($app);
    HandleExceptions::flushState($this);

    expect(get_error_handler())->toBe($errorHandler)
        ->and(get_exception_handler())->toBe($exceptionHandler);
});
