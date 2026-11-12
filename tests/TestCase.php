<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if (! $app->environment('testing')
            || $app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || filled($app['config']->get('database.connections.sqlite.url'))
            || ! $app['config']->get('database.connections.sqlite.foreign_key_constraints')
            || filled($app['config']->get('session.connection'))) {
            throw new RuntimeException('Tests require isolated SQLite :memory: storage with foreign keys and the default session connection.');
        }

        if ($app['db']->connection()->selectOne('PRAGMA database_list')->file !== '') {
            throw new RuntimeException('The test database must not use a file.');
        }

        return $app;
    }
}
