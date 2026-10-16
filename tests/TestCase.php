<?php

namespace RobertoGallea\ClaudeHooks\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use RobertoGallea\ClaudeHooks\ClaudeHooksServiceProvider;
use RobertoGallea\ClaudeHooks\Testing\InteractsWithClaudeHooks;

abstract class TestCase extends Orchestra
{
    use InteractsWithClaudeHooks;

    protected function getPackageProviders($app): array
    {
        return [ClaudeHooksServiceProvider::class];
    }

    /** database_path('migrations') is the test migrations folder, so the default migration path holds real migrations. */
    protected function defineEnvironment($app): void
    {
        $app->useDatabasePath(__DIR__.'/database');
        $app['config']->set('database.default', 'testing');
    }

    /** Tests that refresh the database get the test migrations run and recorded in the migrations table. */
    protected function defineDatabaseMigrations(): void
    {
        if (static::usesRefreshDatabaseTestingConcern()) {
            $this->loadMigrationsFrom(database_path('migrations'));
        }
    }
}
