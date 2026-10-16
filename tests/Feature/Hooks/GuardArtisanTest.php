<?php

use Illuminate\Support\Facades\Artisan;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Hooks\GuardArtisan;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;

beforeEach(function () {
    enableReadyMadeHook('guard-artisan');
});

it('is registered for Bash, only for Artisan commands', function () {
    $this->assertRegistered(GuardArtisan::class, Event::PreToolUse, matcher: 'Bash', if: 'Bash(php artisan *)');
});

/** Point the default connection at a MySQL database on this host, without connecting to it. */
function mysqlOn(string $host): void
{
    config([
        'database.default' => 'mysql',
        'database.connections.mysql' => ['driver' => 'mysql', 'host' => $host, 'database' => 'app'],
    ]);
}

it('denies a destructive Artisan command on a database that is not local, telling Claude to ask the user', function () {
    mysqlOn('db.prod.internal');

    expect($this->runHook(GuardArtisan::class, PreToolUseInput::fake()->bash('php artisan migrate:fresh --seed'))->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'PreToolUse',
            'permissionDecision' => 'deny',
            'permissionDecisionReason' => "migrate:fresh would run against the 'mysql' connection, which is not a local database. Ask the user to run it themselves.",
        ],
    ]);
});

it('lets a destructive Artisan command through on SQLite, in any environment', function () {
    config(['database.connections.testing.driver' => 'sqlite']);
    app()->detectEnvironment(fn () => 'production');

    $this->runHook(GuardArtisan::class, PreToolUseInput::fake()->bash('php artisan migrate:fresh --seed'))->assertNoOpinion();
});

it('lets a destructive Artisan command through on a local host when the app is local', function (string $host) {
    mysqlOn($host);
    app()->detectEnvironment(fn () => 'local');

    $this->runHook(GuardArtisan::class, PreToolUseInput::fake()->bash('php artisan db:wipe --force'))->assertNoOpinion();
})->with(['127.0.0.1', 'localhost', 'mysql', 'pgsql']);

it('denies a destructive Artisan command on a local host when the app is not local', function () {
    mysqlOn('127.0.0.1');
    app()->detectEnvironment(fn () => 'staging');

    $this->runHook(GuardArtisan::class, PreToolUseInput::fake()->bash('php artisan db:wipe --force'))
        ->assertDenied("db:wipe would run against the 'mysql' connection, which is not a local database.");
});

it('counts only the configured hosts as local', function () {
    enableReadyMadeHook('guard-artisan', ['local_hosts' => ['database.test']]);
    app()->detectEnvironment(fn () => 'local');

    mysqlOn('database.test');
    $this->runHook(GuardArtisan::class, PreToolUseInput::fake()->bash('php artisan migrate:fresh'))->assertNoOpinion();

    mysqlOn('127.0.0.1');
    $this->runHook(GuardArtisan::class, PreToolUseInput::fake()->bash('php artisan migrate:fresh'))->assertDenied('migrate:fresh');
});

it('asks the user instead of denying when its mode is ask', function () {
    enableReadyMadeHook('guard-artisan', ['mode' => 'ask']);
    mysqlOn('db.prod.internal');

    expect($this->runHook(GuardArtisan::class, PreToolUseInput::fake()->bash('php artisan migrate:rollback'))->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'PreToolUse',
            'permissionDecision' => 'ask',
            'permissionDecisionReason' => "migrate:rollback would run against the 'mysql' connection, which is not a local database. Ask the user to run it themselves.",
        ],
    ]);
});

it('denies every destructive Artisan command on a database that is not local, also inside a compound command', function (string $command, string $named) {
    mysqlOn('db.prod.internal');

    $this->runHook(GuardArtisan::class, PreToolUseInput::fake()->bash($command))
        ->assertDenied("{$named} would run against the 'mysql' connection");
})->with([
    'migrate:fresh' => ['php artisan migrate:fresh', 'migrate:fresh'],
    'migrate:refresh' => ['php artisan migrate:refresh --seed', 'migrate:refresh'],
    'migrate:reset' => ['php artisan migrate:reset', 'migrate:reset'],
    'migrate:rollback' => ['php artisan migrate:rollback --step=2', 'migrate:rollback'],
    'db:wipe' => ['php artisan db:wipe --force', 'db:wipe'],
    'after cd' => ['cd /srv/app && php artisan db:wipe --force', 'db:wipe'],
    'after another Artisan command' => ['php artisan about; php artisan migrate:fresh --seed', 'migrate:fresh'],
    'with ./artisan' => ['./artisan migrate:fresh', 'migrate:fresh'],
]);

it('has no opinion on an Artisan command that is not destructive, whatever the database', function (string $command) {
    mysqlOn('db.prod.internal');

    $this->runHook(GuardArtisan::class, PreToolUseInput::fake()->bash($command))->assertNoOpinion();
})->with([
    'migrate' => ['php artisan migrate'],
    'migrate:status' => ['php artisan migrate:status'],
    'db:seed' => ['php artisan db:seed'],
    'db:show' => ['php artisan db:show'],
    'a longer command name' => ['php artisan migrate:freshen'],
    'not Artisan' => ['echo "remember to run migrate:fresh"'],
]);

it('guards the configured destructive commands instead of the default ones', function () {
    enableReadyMadeHook('guard-artisan', ['destructive' => 'db:wipe|tenants:purge']);
    mysqlOn('db.prod.internal');

    $this->runHook(GuardArtisan::class, PreToolUseInput::fake()->bash('php artisan tenants:purge --all'))
        ->assertDenied("tenants:purge would run against the 'mysql' connection");
    $this->runHook(GuardArtisan::class, PreToolUseInput::fake()->bash('php artisan migrate:fresh'))->assertNoOpinion();
});

it('checks the connection the command names with --database instead of the default one', function () {
    config(['database.connections.reporting' => ['driver' => 'pgsql', 'host' => 'reports.internal']]);

    $this->runHook(GuardArtisan::class, PreToolUseInput::fake()->bash('php artisan migrate:fresh --database=reporting'))
        ->assertDenied("migrate:fresh would run against the 'reporting' connection");
    $this->runHook(GuardArtisan::class, PreToolUseInput::fake()->bash('php artisan db:wipe --database testing'))
        ->assertNoOpinion();
});

it('checks every destructive Artisan command in a compound command, not only the first', function () {
    config(['database.connections.reporting' => ['driver' => 'pgsql', 'host' => 'reports.internal']]);

    $this->runHook(GuardArtisan::class, PreToolUseInput::fake()->bash('php artisan migrate:fresh && php artisan db:wipe --database=reporting'))
        ->assertDenied("db:wipe would run against the 'reporting' connection");
});

it('is not http-safe, since it reads the database Claude Code\'s environment points at', function () {
    expect(GuardArtisan::httpSafe())->toBeFalse();
});

it('is registered as an Artisan command once enabled', function () {
    expect(Artisan::all())->toHaveKey('claude-hooks:guard-artisan');
});
