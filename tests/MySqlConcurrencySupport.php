<?php

use App\Services\ImapMailboxService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\RegisterFacades;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Bootstrap\SetRequestForConsole;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\TestCase;

final class Acc02Statement extends PDOStatement
{
    protected function __construct(private stdClass $trace) {}

    public function execute(?array $params = null): bool
    {
        try {
            return parent::execute($params);
        } catch (PDOException $exception) {
            $this->trace->errors[] = ['sqlstate' => $exception->errorInfo[0] ?? null, 'driver' => $exception->errorInfo[1] ?? null];
            throw $exception;
        }
    }
}

final class Acc02Environment
{
    public static function credentials(): array
    {
        $file = '/home/wiley/.office-acceptance.cnf';
        self::ensure(is_file($file) && ! is_link($file) && (fileperms($file) & 0077) === 0, 'private_credentials');
        $profile = @parse_ini_file($file, true, INI_SCANNER_RAW)['client'] ?? [];
        self::ensure(($profile['user'] ?? null) === 'office_acc_test' && ($profile['host'] ?? null) === '127.0.0.1' && ($profile['database'] ?? null) === 'office_groups_acceptance' && ($profile['port'] ?? '3306') === '3306' && is_string($profile['password'] ?? null) && $profile['password'] !== '', 'dedicated_credentials');

        return $profile;
    }

    public static function ensure(bool $condition, string $label): void
    {
        if (! $condition) {
            throw new RuntimeException('ACC02 guard: '.$label);
        }
    }

    public static function identity(PDO $pdo): int
    {
        $row = $pdo->query('SELECT DATABASE() AS db, CURRENT_USER() AS account, CONNECTION_ID() AS id')->fetch(PDO::FETCH_ASSOC);
        self::ensure($row['db'] === 'office_groups_acceptance' && $row['account'] === 'office_acc_test@127.0.0.1', 'sql_identity');

        return (int) $row['id'];
    }

    public static function preflight(PDO $pdo): void
    {
        self::identity($pdo);
        self::ensure(str_starts_with($pdo->query('SELECT VERSION()')->fetchColumn(), '8.0.'), 'mysql_version');
        $grants = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
        $usage = false;
        $schema = false;
        foreach ($grants as $grant) {
            self::ensure(stripos($grant, 'WITH GRANT OPTION') === false, 'grant_option');
            if (str_starts_with($grant, 'GRANT USAGE ON *.* TO ')) {
                $usage = true;
            } elseif (str_starts_with($grant, 'GRANT ALL PRIVILEGES ON `office_groups_acceptance`.* TO ')) {
                $schema = true;
            } else {
                throw new RuntimeException('ACC02 guard: unexpected_grant');
            }
        }
        self::ensure(count($grants) === 2 && $usage && $schema && $pdo->query('SELECT CURRENT_ROLE()')->fetchColumn() === 'NONE', 'grant_scope');
        $actual = $pdo->query('SELECT migration FROM migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN);
        $expected = array_map(fn (string $file): string => pathinfo($file, PATHINFO_FILENAME), glob(dirname(__DIR__).'/database/migrations/*.php'));
        sort($expected);
        self::ensure($actual === $expected && count($actual) === 24, 'migration_ledger');
        self::ensure((int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='office_groups_acceptance'")->fetchColumn() === 32 && (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='office_groups_acceptance' AND engine='InnoDB'")->fetchColumn() === 32, 'innodb_schema');
        self::ensure((int) $pdo->query("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema='office_groups_acceptance'")->fetchColumn() === 39 && (int) $pdo->query('SELECT @@foreign_key_checks')->fetchColumn() === 1, 'foreign_keys');
    }

    public static function boot(#[SensitiveParameter] string $key): array
    {
        self::ensure(str_starts_with($key, 'base64:') && strlen(base64_decode(substr($key, 7), true)) === 32, 'synthetic_app_key');
        $credentials = self::credentials();
        $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=office_groups_acceptance;charset=utf8mb4', $credentials['user'], $credentials['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        self::preflight($pdo);
        $pdo = null;
        foreach (['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/acc02-no-cache-'.bin2hex(random_bytes(8)).'.php'] as $name => $value) {
            putenv($name.'='.$value);
            $_ENV[$name] = $_SERVER[$name] = $value;
            Env::getRepository()->set($name, $value);
        }
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $app->loadEnvironmentFrom('.env.office-acceptance-disabled');
        self::ensure(! $app->configurationIsCached() && ! is_file($app->environmentFilePath()) && ! is_file($app->environmentFilePath().'.testing'), 'no_env_or_config_cache');
        $trace = (object) ['errors' => [], 'begins' => 0, 'commits' => 0, 'rollbacks' => 0];
        $writesAllowed = false;
        $app->afterBootstrapping(LoadConfiguration::class, function () use ($app, $key, $credentials, $trace, &$writesAllowed): void {
            $app['config']->set([
                'app.env' => 'testing', 'app.debug' => false, 'app.key' => $key, 'app.previous_keys' => [],
                'database.default' => 'acceptance',
                'database.connections' => ['acceptance' => [
                    'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'office_groups_acceptance',
                    'username' => 'office_acc_test', 'password' => $credentials['password'], 'url' => null, 'unix_socket' => '',
                    'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true, 'engine' => 'InnoDB',
                ]],
                'session.driver' => 'array', 'session.connection' => 'acceptance',
                'cache.default' => 'array', 'permission.cache.store' => 'array',
                'queue.default' => 'sync', 'queue.failed.driver' => 'null', 'queue.batching.database' => 'acceptance', 'queue.failed.database' => 'acceptance',
                'mail.default' => 'array', 'logging.default' => 'null', 'hashing.bcrypt.rounds' => 4,
                'seeding.profiler' => [],
            ]);
            $app->resolving('db', function (DatabaseManager $manager) use ($trace, &$writesAllowed): void {
                $connection = $manager->connection('acceptance');
                $connection->beforeExecuting(function (string $sql) use ($connection, &$writesAllowed): void {
                    self::identity($connection->getPdo());
                    self::ensure((bool) preg_match('/^\s*(SELECT|SHOW|DESCRIBE|INSERT|UPDATE|DELETE|SAVEPOINT|RELEASE|ROLLBACK)\b/i', $sql), 'no_ddl_or_connection_changes');
                    self::ensure($writesAllowed || (bool) preg_match('/^\s*(SELECT|SHOW|DESCRIBE)\b/i', $sql), 'bootstrap_writes_disabled');
                });
                $connection->getPdo()->setAttribute(PDO::ATTR_STATEMENT_CLASS, [Acc02Statement::class, [$trace]]);
                $connection->getPdo()->exec('SET SESSION innodb_lock_wait_timeout = 15');
            });
        });
        $app->bootstrapWith([LoadConfiguration::class, HandleExceptions::class, RegisterFacades::class, SetRequestForConsole::class, RegisterProviders::class, BootProviders::class]);
        $app->make(Kernel::class)->bootstrap();
        $app->setLocale('en');
        Mail::fake();
        Queue::fake();
        Http::preventStrayRequests();
        $app->instance(ImapMailboxService::class, Mockery::mock(ImapMailboxService::class));
        $connection = $app['db']->connection();
        self::preflight($connection->getPdo());
        self::ensure(array_keys(config('database.connections')) === ['acceptance'] && config('database.default') === 'acceptance' && ! $app->configurationIsCached(), 'connection_quarantine');
        foreach (['TransactionBeginning' => 'begins', 'TransactionCommitted' => 'commits', 'TransactionRolledBack' => 'rollbacks'] as $event => $counter) {
            $app['events']->listen('Illuminate\\Database\\Events\\'.$event, function () use ($trace, $counter): void {
                $trace->{$counter}++;
            });
        }
        $writesAllowed = true;

        return [$app, $connection, $trace];
    }

    public static function shutdown(?TestCase $test = null): void
    {
        HandleExceptions::flushState($test);
    }
}

final class Acc02Process
{
    private mixed $process = null;

    private array $pipes = [];

    public readonly int $connectionId;

    public function __construct(#[SensitiveParameter] array $configuration)
    {
        $this->process = proc_open([PHP_BINARY, __DIR__.'/MySqlConcurrencyWorker.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $this->pipes, dirname(__DIR__));
        Acc02Environment::ensure(is_resource($this->process), 'worker_start');
        $this->send($configuration);
        $ready = $this->receive(30);
        Acc02Environment::ensure(($ready['event'] ?? null) === 'ready', 'worker_preflight');
        $this->connectionId = $ready['connection_id'];
    }

    public function __destruct()
    {
        $this->stop();
    }

    public function send(#[SensitiveParameter] array $message): void
    {
        Acc02Environment::ensure(fwrite($this->pipes[0], json_encode($message, JSON_THROW_ON_ERROR)."\n") !== false, 'worker_pipe_write');
        fflush($this->pipes[0]);
    }

    public function receive(int $timeout = 20): array
    {
        $read = [$this->pipes[1]];
        $write = $except = null;
        Acc02Environment::ensure(stream_select($read, $write, $except, $timeout) === 1, 'worker_deadline');
        $line = fgets($this->pipes[1]);
        Acc02Environment::ensure(is_string($line), 'worker_exit');

        return json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    }

    public function start(#[SensitiveParameter] array $command): void
    {
        $this->send($command);
        $message = $this->receive();
        Acc02Environment::ensure(($message['event'] ?? null) === 'started', 'worker_started');
    }

    public function result(): array
    {
        $message = $this->receive();
        Acc02Environment::ensure(($message['event'] ?? null) === 'result', 'worker_result');

        return $message;
    }

    public function call(#[SensitiveParameter] array $command): array
    {
        $this->start($command);

        return $this->result();
    }

    public function stop(): void
    {
        if (! is_resource($this->process)) {
            return;
        }
        if (proc_get_status($this->process)['running']) {
            @fwrite($this->pipes[0], "{\"op\":\"stop\"}\n");
            fclose($this->pipes[0]);
            $deadline = microtime(true) + 1;
            while (proc_get_status($this->process)['running'] && microtime(true) < $deadline) {
                usleep(10000);
            }
            if (proc_get_status($this->process)['running']) {
                proc_terminate($this->process, SIGKILL);
            }
        }
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        proc_close($this->process);
    }
}
