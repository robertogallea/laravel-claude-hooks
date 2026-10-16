<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Hooks\SessionContext;
use RobertoGallea\ClaudeHooks\Inputs\SessionStartInput;

uses(RefreshDatabase::class);

beforeEach(function () {
    enableReadyMadeHook('session-context');
});

it('is registered for SessionStart, with no matcher, so that it runs on every source', function () {
    $this->assertRegistered(SessionContext::class, Event::SessionStart);
});

it('tells Claude the versions, environment, default connection and that all migrations have run', function () {
    app()->detectEnvironment(fn () => 'local');

    expect($this->runHook(SessionContext::class, SessionStartInput::fake())->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'SessionStart',
            'additionalContext' => implode("\n", [
                'Laravel '.app()->version().' on PHP '.PHP_VERSION.'.',
                'Environment: local.',
                "Default database connection: 'testing'.",
                'All migrations have run.',
            ]),
        ],
    ]);
});

it('lists every migration as pending when the database has no migrations table yet', function () {
    Schema::drop('migrations');

    $this->runHook(SessionContext::class, SessionStartInput::fake())
        ->assertContextContains("Default database connection: 'testing'.\nPending migrations: 0001_01_01_000000_create_users_table.");
});

it('lists the pending migrations of the configured migration paths', function () {
    enableReadyMadeHook('session-context', ['paths' => [database_path('migrations'), database_path('billing')]]);

    $this->runHook(SessionContext::class, SessionStartInput::fake())
        ->assertContextContains("\nPending migrations: 2026_10_20_000000_create_invoices_table.");
});

it('lists every pending migration of every path, in order', function () {
    enableReadyMadeHook('session-context', ['paths' => [database_path('billing'), database_path('migrations')]]);
    Schema::drop('migrations');

    $this->runHook(SessionContext::class, SessionStartInput::fake())
        ->assertContextContains("\nPending migrations: 0001_01_01_000000_create_users_table, 2026_10_20_000000_create_invoices_table.");
});

it('reads the migration paths of guard-migrations when it has none of its own', function () {
    config(['claude-hooks.hooks.guard-migrations.paths' => [database_path('billing')]]);

    $this->runHook(SessionContext::class, SessionStartInput::fake())
        ->assertContextContains("\nPending migrations: 2026_10_20_000000_create_invoices_table.");
});

it('leaves out the lines turned off in config', function () {
    enableReadyMadeHook('session-context', ['lines' => ['versions' => false, 'migrations' => false]]);
    app()->detectEnvironment(fn () => 'staging');

    expect($this->runHook(SessionContext::class, SessionStartInput::fake())->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'SessionStart',
            'additionalContext' => "Environment: staging.\nDefault database connection: 'testing'.",
        ],
    ]);
});

it('does not touch the database when the pending migrations line is off', function () {
    enableReadyMadeHook('session-context', ['lines' => ['migrations' => false]]);
    config([
        'database.default' => 'mysql',
        'database.connections.mysql' => ['driver' => 'mysql', 'host' => 'db.unreachable.invalid', 'database' => 'app'],
    ]);

    $this->runHook(SessionContext::class, SessionStartInput::fake())
        ->assertContextContains("Default database connection: 'mysql'.");

    config(['database.default' => 'testing']); // RefreshDatabase rolls back the default connection
});

it('has no opinion when every line is off', function () {
    enableReadyMadeHook('session-context', ['lines' => ['versions' => false, 'environment' => false, 'connection' => false, 'migrations' => false]]);

    $this->runHook(SessionContext::class, SessionStartInput::fake())->assertNoOpinion();
});

it('is not http-safe, since it reports the environment and database Claude Code\'s environment points at', function () {
    expect(SessionContext::httpSafe())->toBeFalse();
});

it('is registered as an Artisan command once enabled', function () {
    expect(Artisan::all())->toHaveKey('claude-hooks:session-context');
});
