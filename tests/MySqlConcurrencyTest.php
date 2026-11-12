<?php

use App\Models\Access;
use App\Models\Group;
use App\Models\User;
use App\Services\AccessEncryptionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require_once __DIR__.'/MySqlConcurrencySupport.php';

/**
 * OFFICE_MYSQL_CONCURRENCY=1 vendor/bin/pest tests/MySqlConcurrencyTest.php --bail --fail-on-risky
 * No migrations, reset, outer fixture transaction or development connection.
 */
test('ACC-02 real InnoDB concurrency', function (string $scenario): void {
    if (getenv('OFFICE_MYSQL_CONCURRENCY') !== '1') {
        $this->markTestSkipped('Explicit OFFICE_MYSQL_CONCURRENCY=1 is required.');
    }

    $connection = null;
    $workers = [];
    $fixture = null;
    $diagnostics = ['scenario' => $scenario];
    $stage = 'preflight';
    $check = function (bool $condition, string $label): void {
        expect($condition)->toBeTrue($label);
    };
    $inventory = static function () use (&$connection): array {
        $result = [];
        foreach ($connection->select("SELECT TABLE_NAME AS name FROM information_schema.tables WHERE table_schema='office_groups_acceptance' ORDER BY TABLE_NAME") as $row) {
            $result[$row->name] = $connection->table($row->name)->count();
        }

        return $result;
    };
    $baselineState = static function () use (&$connection): string {
        return hash('sha256', json_encode([
            $connection->table('users')->orderBy('id')->get()->all(),
            $connection->table('roles')->orderBy('id')->get()->all(),
            $connection->table('model_has_roles')->orderBy('model_id')->get()->all(),
            $connection->table('migrations')->orderBy('id')->get()->all(),
        ], JSON_THROW_ON_ERROR));
    };
    $summarize = static function (string $label, array $result, int $expectedTransactions = 1) use (&$diagnostics): array {
        $diagnostics[$label] = ['kind' => $result['kind'], 'updates' => $result['updates'], 'expected_transactions' => $expectedTransactions, ...$result['trace']];
        if (isset($result['data']['fields'])) {
            $diagnostics[$label]['fields'] = $result['data']['fields'];
        }
        if (isset($result['data']['exception_class'])) {
            $diagnostics[$label]['exception_class'] = $result['data']['exception_class'];
        }

        return $result;
    };

    try {
        $key = 'base64:'.base64_encode(random_bytes(32));
        [$app, $connection, $trace] = Acc02Environment::boot($key);
        $initialInventory = $inventory();
        foreach ($initialInventory as $table => $count) {
            $expectedCount = match ($table) {
                'migrations' => 24, 'roles' => 3, 'users', 'model_has_roles' => 1, default => 0
            };
            $check($count === $expectedCount, 'Initial acceptance inventory: '.$table);
        }
        $check($connection->table('roles')->orderBy('name')->pluck('name')->all() === ['admin', 'profiler', 'user'], 'The system roles remain intact.');
        $baseline = $baselineState();
        $tag = 'acc02-'.strtolower($scenario).'-'.bin2hex(random_bytes(6));
        $stage = 'fixture creation';
        $fixture = $connection->transaction(function () use ($tag): array {
            $g = Group::factory()->create(['name' => $tag.'-G']);
            $h = Group::factory()->create(['name' => $tag.'-H']);
            $users = [];
            foreach (['A' => $g, 'B' => $g, 'C' => $h] as $label => $group) {
                $users[$label] = User::factory()->officeUser($group)->create(['name' => $tag.'-'.$label, 'email' => $tag.'-'.strtolower($label).'@example.test', 'password' => bin2hex(random_bytes(24)).'aA1!'])->id;
            }
            $admin = User::factory()->create(['name' => $tag.'-admin', 'email' => $tag.'-admin@example.test', 'password' => bin2hex(random_bytes(24)).'aA1!']);
            $admin->assignRole('admin');
            $users['admin'] = $admin->id;

            return ['tag' => $tag, 'groups' => [$g->id, $h->id], 'users' => array_values($users), 'actors' => $users];
        });
        [$g, $h] = $fixture['groups'];
        $actor = static function (int $id): void {
            Auth::guard()->setUser(User::query()->findOrFail($id));
        };
        $service = app(AccessEncryptionService::class);
        $create = function (int $user, string $plaintext) use ($actor, $service, $tag): int {
            $actor($user);
            $context = $service->context();
            $payload = $service->encryptData($plaintext, $context);

            return $service->create(['name' => $tag.'-record', 'category' => $tag, 'content' => $payload, 'is_encrypted' => true, 'encryption_context' => $context])->id;
        };
        $snapshot = static function (int $group): array {
            return [
                'key' => Group::query()->findOrFail($group)->getRawOriginal('access_encryption_key'),
                'records' => Access::query()->where('group_id', $group)->orderBy('id')->get()->map(fn (Access $record): array => $record->getRawOriginal())->all(),
            ];
        };
        $consistent = function (int $user, int $group, array $expected) use ($actor, $service, $check): void {
            $actor($user);
            $context = $service->context();
            $check(Access::query()->where('group_id', $group)->count() === count($expected), 'No lost or unexpected encrypted records.');
            foreach ($expected as $id => $plaintext) {
                $record = Access::query()->findOrFail($id);
                $check($record->group_id === $group && hash_equals($plaintext, $service->decryptData($record->content, $context, $record, $record->version())), 'Committed data matches the committed group key.');
            }
        };
        $spawn = function (int $user) use (&$workers, $fixture, $key): Acc02Process {
            $worker = new Acc02Process(['opt_in' => 'ACC02', 'fixture' => $fixture, 'actor' => $user, 'key' => $key]);
            $workers[] = $worker;

            return $worker;
        };
        $observeWait = function (Acc02Process $worker, string $table) use ($connection, &$diagnostics, $check): void {
            $start = hrtime(true);
            $deadline = microtime(true) + 8;
            while (microtime(true) < $deadline) {
                foreach ($connection->getPdo()->query('SHOW PROCESSLIST')->fetchAll(PDO::FETCH_ASSOC) as $process) {
                    $sql = strtolower((string) ($process['Info'] ?? ''));
                    if ((int) $process['Id'] === $worker->connectionId && str_contains($sql, '`'.$table.'`') && ($table === 'groups' ? str_contains($sql, 'for update') : str_starts_with($sql, 'update'))) {
                        $diagnostics['lock_wait'] = ['connection_id' => $worker->connectionId, 'table' => $table, 'server_state' => $process['State'], 'observed_after_ms' => round((hrtime(true) - $start) / 1000000, 2)];
                        $check(true, 'The server sees the conflicting statement while the holder is paused inside its transaction.');

                        return;
                    }
                }
                usleep(10000);
            }
            throw new RuntimeException('ACC02 barrier: conflicting server statement was not observed.');
        };
        $pauseRotation = function (Acc02Process $worker, string $context) use ($check): void {
            $worker->start(['op' => 'rotate', 'context' => $context, 'pause' => 'first_access']);
            $message = $worker->receive();
            $check(($message['event'] ?? null) === 'paused' && $message['point'] === 'first_access', 'Rotation is paused after a real ciphertext UPDATE before commit.');
        };
        $stage = $scenario;
        $expected = [];
        if ($scenario !== 'C01') {
            foreach (range(1, 2) as $index) {
                $plaintext = bin2hex(random_bytes(24));
                $expected[$create($fixture['actors']['A'], $plaintext)] = $plaintext;
            }
        }
        $actor($fixture['actors']['A']);
        $context = $service->context();
        $beforeG = $snapshot($g);
        $a = $spawn($fixture['actors']['A']);
        $b = $spawn($fixture['actors'][match ($scenario) {
            'C05' => 'C', 'C06' => 'admin', default => 'B'
        }]);
        $check($a->connectionId !== $b->connectionId, 'Workers have independent MySQL connections.');
        $diagnostics['connections'] = ['A' => $a->connectionId, 'B' => $b->connectionId, 'coordinator' => Acc02Environment::identity($connection->getPdo())];

        if ($scenario === 'C01') {
            $plaintext = bin2hex(random_bytes(24));
            $prepared = $a->call(['op' => 'prepare', 'plaintext' => $plaintext]);
            $check($prepared['kind'] === 'ok', 'A prepares ciphertext using the real service.');
            $written = $summarize('write', $a->call(['op' => 'create', ...$prepared['data']]));
            $check($written['kind'] === 'ok', 'A commits encrypted data before rotation.');
            $expected[$written['data']['record_id']] = $plaintext;
            $rotated = $summarize('rotation', $b->call(['op' => 'rotate', 'context' => $context]));
            $check($rotated['kind'] === 'ok', 'B rotates the key after A commits.');
            $check(Access::query()->findOrFail($written['data']['record_id'])->user_id === $fixture['actors']['A'], 'Rotation preserves original attribution.');
        } elseif (in_array($scenario, ['C02', 'C03'], true)) {
            $prepared = $b->call(['op' => 'prepare', 'plaintext' => bin2hex(random_bytes(24))]);
            $check($prepared['kind'] === 'ok', 'A pending submission has the original context.');
            $pauseRotation($a, $context);
            $b->start($scenario === 'C02' ? ['op' => 'create', ...$prepared['data']] : ['op' => 'rotate', 'context' => $context]);
            $observeWait($b, 'groups');
            $a->send(['op' => 'release']);
            $rotated = $summarize('rotation', $a->result());
            $check($rotated['kind'] === 'ok', 'The lock holder commits rotation.');
            $afterFirst = $snapshot($g);
            $conflict = $summarize('conflict', $b->result());
            $check($conflict['kind'] === 'validation' && in_array('encryption_context', $conflict['data']['fields'], true), 'The blocked stale operation fails closed after the first rotation commits.');
            $check($snapshot($g) === $afterFirst, 'The conflicting operation cannot replace the committed key or ciphertext.');
            if ($scenario === 'C02') {
                $plaintext = bin2hex(random_bytes(24));
                $fresh = $b->call(['op' => 'prepare', 'plaintext' => $plaintext]);
                $stalePayload = $summarize('stale_payload', $b->call(['op' => 'create', 'context' => $fresh['data']['context'], 'payload' => $prepared['data']['payload']]));
                $check($stalePayload['kind'] === 'validation' && in_array('content', $stalePayload['data']['fields'], true), 'Even a fresh context cannot authorize ciphertext under the previous key.');
                $created = $summarize('fresh_write', $b->call(['op' => 'create', ...$fresh['data']]));
                $check($created['kind'] === 'ok', 'A separately prepared fresh write succeeds.');
                $expected[$created['data']['record_id']] = $plaintext;
            }
        } elseif ($scenario === 'C04') {
            foreach (['first_access', 'group_key'] as $point) {
                $failed = $summarize($point, $a->call(['op' => 'rotate', 'context' => $context, 'fault' => $point]));
                $check($failed['kind'] === 'injected' && $failed['updates'] >= 1 && $failed['trace']['rollbacks'] === 1 && $failed['trace']['commits'] === 0, 'A controlled pre-commit failure rolls back the real transaction.');
                $check($snapshot($g) === $beforeG, 'Both stored key and every ciphertext are byte-identical after rollback.');
                $consistent($fixture['actors']['B'], $g, $expected);
            }
        } elseif ($scenario === 'C05') {
            $plaintextH = bin2hex(random_bytes(24));
            $recordH = $create($fixture['actors']['C'], $plaintextH);
            $beforeH = $snapshot($h);
            $pauseRotation($a, $context);
            $ownRead = $summarize('independent_H_read', $b->call(['op' => 'verify', 'record' => $recordH, 'plaintext' => $plaintextH]), 2);
            $check($ownRead['kind'] === 'ok' && $ownRead['data']['matches'], 'Group H remains usable while group G is locked.');
            $a->send(['op' => 'release']);
            $check($summarize('rotation', $a->result())['kind'] === 'ok', 'Group G rotation completes.');
            $recordG = array_key_first($expected);
            $crossRead = $summarize('cross_read', $b->call(['op' => 'verify', 'record' => $recordG, 'plaintext' => $expected[$recordG]]), 2);
            $crossUpdate = $summarize('cross_update', $b->call(['op' => 'foreign_update', 'record' => $recordG]), 2);
            $check($crossRead['kind'] === 'denied' && $crossUpdate['kind'] === 'denied', 'Group H cannot decrypt or update group G.');
            $check($snapshot($h) === $beforeH, 'The unrelated group key and ciphertext remain byte-identical.');
            $consistent($fixture['actors']['C'], $h, [$recordH => $plaintextH]);
        } elseif ($scenario === 'C06') {
            $pauseRotation($a, $context);
            $b->start(['op' => 'reassign', 'target' => $fixture['actors']['A'], 'group' => $h]);
            $observeWait($b, 'users');
            $a->send(['op' => 'release']);
            $check($summarize('rotation', $a->result())['kind'] === 'ok', 'The authorized old-group operation commits before reassignment.');
            $check($summarize('reassignment', $b->result())['kind'] === 'ok', 'The real UserController reassignment commits after the user lock is released.');
            $check(User::query()->findOrFail($fixture['actors']['A'])->group_id === $h, 'Membership changes to the owned target group.');
            $blocked = $summarize('cached_auth_after_move', $a->call(['op' => 'rotate', 'context' => $context]));
            $check($blocked['kind'] === 'denied', 'An authenticated but stale old-group user fails closed after reassignment.');
            $actor($fixture['actors']['A']);
            $record = Access::query()->findOrFail(array_key_first($expected));
            $denied = false;
            try {
                $service->decryptData($record->content, $service->context(), $record, $record->version());
            } catch (ModelNotFoundException|AuthorizationException|HttpExceptionInterface) {
                $denied = true;
            }
            $check($denied, 'A refreshed new-group identity cannot read old-group data.');
        }
        $consistent($fixture['actors']['B'], $g, $expected);
        if ($scenario !== 'C04') {
            $check($snapshot($g)['key'] !== $beforeG['key'], 'A successful rotation replaces the group key.');
        }
        foreach ($diagnostics as $label => $detail) {
            if (is_array($detail) && array_key_exists('errors', $detail)) {
                $check($detail['errors'] === [] && $detail['begins'] === $detail['expected_transactions'], 'No deadlock, timeout or automatic transaction retry in '.$label);
            }
        }
        fwrite(STDOUT, "\nACC02 ".$scenario.' PASS '.json_encode($diagnostics, JSON_THROW_ON_ERROR)."\n");
    } catch (Throwable $exception) {
        $detail = $exception instanceof AssertionFailedError ? $exception->getMessage() : get_class($exception);
        fwrite(STDOUT, "\nACC02 sanitized diagnostic ".json_encode($diagnostics, JSON_THROW_ON_ERROR)."\n");
        throw new RuntimeException('ACC02 stopped at '.$stage.': '.$detail.'; secret values suppressed.');
    } finally {
        try {
            foreach ($workers as $worker) {
                $worker->stop();
            }
            if ($fixture !== null && $connection !== null) {
                $connection->transaction(function () use ($fixture, $connection): void {
                    Acc02Environment::identity($connection->getPdo());
                    $groups = Group::query()->whereIn('id', $fixture['groups'])->lockForUpdate()->get();
                    $users = User::query()->whereIn('id', $fixture['users'])->lockForUpdate()->get();
                    Acc02Environment::ensure($groups->count() === 2 && $groups->every(fn (Group $group): bool => in_array($group->name, [$fixture['tag'].'-G', $fixture['tag'].'-H'], true)), 'cleanup_group_ownership');
                    Acc02Environment::ensure($users->count() === 4 && $users->every(fn (User $user): bool => str_starts_with($user->email, $fixture['tag'].'-') && ($user->group_id === null || in_array($user->group_id, $fixture['groups'], true))), 'cleanup_user_ownership');
                    Acc02Environment::ensure(! User::query()->whereIn('group_id', $fixture['groups'])->whereNotIn('id', $fixture['users'])->exists(), 'cleanup_no_other_members');
                    $records = Access::query()->whereIn('group_id', $fixture['groups'])->lockForUpdate()->get();
                    Acc02Environment::ensure($records->every(fn (Access $record): bool => $record->category === $fixture['tag'] && in_array($record->user_id, $fixture['users'], true)), 'cleanup_record_ownership');
                    Access::query()->whereIn('id', $records->modelKeys())->where('category', $fixture['tag'])->delete();
                    $connection->table('model_has_roles')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $fixture['users'])->delete();
                    User::query()->whereIn('id', $fixture['users'])->where('email', 'like', $fixture['tag'].'-%')->delete();
                    Group::query()->whereIn('id', $fixture['groups'])->whereIn('name', [$fixture['tag'].'-G', $fixture['tag'].'-H'])->delete();
                });
                $check($inventory() === $initialInventory && $baselineState() === $baseline, 'Cleanup restores the exact baseline counts and preserves the bootstrap profiler and system roles.');
                fwrite(STDOUT, 'ACC02 '.$scenario." cleanup PASS\n");
            }
        } finally {
            $connection?->disconnect();
            Acc02Environment::shutdown($this);
        }
    }
})->with(['C01', 'C02', 'C03', 'C04', 'C05', 'C06']);
