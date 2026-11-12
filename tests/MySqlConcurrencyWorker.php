<?php

use App\Http\Controllers\UserController;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Access;
use App\Models\Group;
use App\Models\User;
use App\Services\AccessEncryptionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

ini_set('display_errors', '0');
ini_set('log_errors', '0');
require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/MySqlConcurrencySupport.php';

function acc02Emit(array $message): void
{
    fwrite(STDOUT, json_encode($message, JSON_THROW_ON_ERROR)."\n");
    fflush(STDOUT);
}

function acc02Input(int $timeout = 20): array
{
    $read = [STDIN];
    $write = $except = null;
    Acc02Environment::ensure(stream_select($read, $write, $except, $timeout) === 1, 'control_deadline');
    $line = fgets(STDIN);
    Acc02Environment::ensure(is_string($line), 'control_eof');

    return json_decode($line, true, 512, JSON_THROW_ON_ERROR);
}

$connection = null;
try {
    $configuration = acc02Input();
    Acc02Environment::ensure(($configuration['opt_in'] ?? null) === 'ACC02', 'worker_opt_in');
    [$app, $connection, $trace] = Acc02Environment::boot($configuration['key']);
    $fixture = $configuration['fixture'];
    $actor = User::query()->findOrFail($configuration['actor']);
    Acc02Environment::ensure(in_array($actor->id, $fixture['users'], true) && str_starts_with($actor->email, $fixture['tag'].'-'), 'worker_actor_ownership');
    Auth::guard()->setUser($actor);
    $service = $app->make(AccessEncryptionService::class);
    $control = ['op' => '', 'updated' => 0];
    $barrier = function (string $point) use (&$control): void {
        if (($control['pause'] ?? '') === $point) {
            acc02Emit(['event' => 'paused', 'point' => $point]);
            Acc02Environment::ensure((acc02Input()['op'] ?? null) === 'release', 'barrier_release');
        }
        if (($control['fault'] ?? '') === $point) {
            throw new RuntimeException('Controlled acceptance failure.', 20004);
        }
    };
    Access::updated(function (Access $access) use (&$control, $fixture, $barrier): void {
        Acc02Environment::ensure($access->category === $fixture['tag'] && in_array($access->group_id, $fixture['groups'], true), 'worker_updated_record_ownership');
        if ($control['op'] === 'rotate' && $access->wasChanged('content')) {
            $control['updated']++;
            if ($control['updated'] === 1) {
                $barrier('first_access');
            }
        }
    });
    Group::updated(function (Group $group) use (&$control, $fixture, $barrier): void {
        Acc02Environment::ensure(in_array($group->id, $fixture['groups'], true) && Group::query()->whereKey($group->id)->whereIn('name', [$fixture['tag'].'-G', $fixture['tag'].'-H'])->exists(), 'worker_updated_group_ownership');
        if ($control['op'] === 'rotate' && $group->wasChanged('access_encryption_key')) {
            $barrier('group_key');
        }
    });
    acc02Emit(['event' => 'ready', 'connection_id' => Acc02Environment::identity($connection->getPdo())]);
    while (true) {
        $command = acc02Input(40);
        if (($command['op'] ?? null) === 'stop') {
            break;
        }
        Acc02Environment::preflight($connection->getPdo());
        Acc02Environment::ensure(User::query()->whereKey($actor->id)->where('email', 'like', $fixture['tag'].'-%')->exists(), 'worker_phase_ownership');
        $control = [...$command, 'updated' => 0];
        $trace->errors = [];
        $trace->begins = $trace->commits = $trace->rollbacks = 0;
        acc02Emit(['event' => 'started', 'op' => $command['op']]);
        $kind = 'ok';
        $result = [];
        try {
            switch ($command['op']) {
                case 'prepare':
                    $context = $service->context();
                    $result = ['context' => $context, 'payload' => $service->encryptData($command['plaintext'], $context)];
                    break;
                case 'create':
                    $record = $service->create(['name' => $fixture['tag'].'-record', 'category' => $fixture['tag'], 'content' => $command['payload'], 'is_encrypted' => true, 'encryption_context' => $command['context']]);
                    $result = ['record_id' => $record->id];
                    break;
                case 'rotate':
                    $service->rotateKey($command['context']);
                    break;
                case 'verify':
                    $record = Access::query()->findOrFail($command['record']);
                    Acc02Environment::ensure($record->category === $fixture['tag'], 'worker_read_record_ownership');
                    $context = $service->context();
                    $result = ['matches' => hash_equals($command['plaintext'], $service->decryptData($record->content, $context, $record, $record->version()))];
                    break;
                case 'foreign_update':
                    $record = Access::query()->findOrFail($command['record']);
                    Acc02Environment::ensure($record->category === $fixture['tag'], 'worker_update_record_ownership');
                    $service->update($record, ['name' => $record->name, 'category' => $fixture['tag'], 'content' => $record->content, 'is_encrypted' => true, 'encryption_context' => $service->context(), 'version' => $record->version()]);
                    break;
                case 'reassign':
                    Acc02Environment::ensure(in_array($command['target'], $fixture['users'], true) && in_array($command['group'], $fixture['groups'], true), 'reassignment_ownership');
                    $target = User::query()->findOrFail($command['target']);
                    $request = UpdateUserRequest::create('/users/'.$target->id, 'PUT', ['name' => $target->name, 'email' => $target->email, 'status' => 1, 'group_id' => $command['group']]);
                    $route = new Route(['PUT'], 'users/{user}', fn (): null => null);
                    $route->bind($request);
                    $route->setParameter('user', $target);
                    $request->setRouteResolver(fn (): Route => $route);
                    $request->setUserResolver(fn (): User => $actor);
                    $request->setContainer($app)->setRedirector($app['redirect']);
                    $request->validateResolved();
                    $app->make(UserController::class)->update($request, $target);
                    break;
                default:
                    throw new RuntimeException('Unknown acceptance operation.');
            }
        } catch (ValidationException $exception) {
            $kind = 'validation';
            $result = ['fields' => array_keys($exception->errors())];
        } catch (AuthorizationException|ModelNotFoundException $exception) {
            $kind = 'denied';
        } catch (HttpExceptionInterface $exception) {
            $kind = in_array($exception->getStatusCode(), [403, 404], true) ? 'denied' : 'unexpected';
        } catch (Throwable $exception) {
            $kind = $exception instanceof RuntimeException && $exception->getCode() === 20004 ? 'injected' : 'unexpected';
            $result = ['exception_class' => get_class($exception)];
            if ($exception instanceof RuntimeException && str_starts_with($exception->getMessage(), 'ACC02 guard: ')) {
                $result['guard'] = $exception->getMessage();
            }
        }
        acc02Emit(['event' => 'result', 'kind' => $kind, 'data' => $result, 'updates' => $control['updated'], 'trace' => ['begins' => $trace->begins, 'commits' => $trace->commits, 'rollbacks' => $trace->rollbacks, 'errors' => $trace->errors]]);
        Acc02Environment::ensure($connection->transactionLevel() === 0, 'worker_transaction_finished');
    }
} catch (Throwable $exception) {
    acc02Emit(['event' => 'fatal', 'exception_class' => get_class($exception)]);
    exit(1);
} finally {
    $connection?->disconnect();
    Acc02Environment::shutdown();
}
