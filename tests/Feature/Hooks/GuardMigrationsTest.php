<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Hooks\GuardMigrations;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;

/** A subclass that changes the rule: every migration is protected, whether it ran or not. */
class GuardEveryMigration extends GuardMigrations
{
    protected $signature = 'app:guard-every-migration';

    protected function hasRun(string $migration): bool
    {
        return true;
    }
}

uses(RefreshDatabase::class);

beforeEach(function () {
    enableReadyMadeHook('guard-migrations');
});

function ranMigration(): string
{
    return database_path('migrations/0001_01_01_000000_create_users_table.php');
}

it('denies edits to a migration that already ran, telling Claude to create a new one', function () {
    expect($this->runHook(GuardMigrations::class, PreToolUseInput::fake()->edit(ranMigration()))->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'PreToolUse',
            'permissionDecision' => 'deny',
            'permissionDecisionReason' => '0001_01_01_000000_create_users_table has already run on this database. Create a new migration instead of editing it.',
        ],
    ]);
});

it('lets Claude edit or write a migration that has not run yet', function () {
    $pending = database_path('migrations/2026_10_20_000000_create_orders_table.php');

    $this->runHook(GuardMigrations::class, PreToolUseInput::fake()->edit($pending))->assertNoOpinion();
    $this->runHook(GuardMigrations::class, PreToolUseInput::fake()->write($pending))->assertNoOpinion();
});

it('has no opinion on a file outside the migration paths, even with the name of a migration that ran', function () {
    $this->runHook(GuardMigrations::class, PreToolUseInput::fake()->edit(base_path('backup/0001_01_01_000000_create_users_table.php')))
        ->assertNoOpinion();
});

it('guards the configured migration paths instead of the default one', function () {
    enableReadyMadeHook('guard-migrations', ['paths' => ['/srv/app/modules/billing/migrations']]);

    $this->runHook(GuardMigrations::class, PreToolUseInput::fake()->edit('/srv/app/modules/billing/migrations/0001_01_01_000000_create_users_table.php'))
        ->assertDenied('0001_01_01_000000_create_users_table has already run');
    $this->runHook(GuardMigrations::class, PreToolUseInput::fake()->edit(ranMigration()))
        ->assertNoOpinion();
});

it('resolves a relative file path against the session working directory', function () {
    $input = PreToolUseInput::fake(['cwd' => dirname(database_path())])
        ->edit('database/migrations/0001_01_01_000000_create_users_table.php');

    $this->runHook(GuardMigrations::class, $input)->assertDenied('has already run');
});

it('asks the user instead of denying when its mode is ask', function () {
    enableReadyMadeHook('guard-migrations', ['mode' => 'ask']);

    expect($this->runHook(GuardMigrations::class, PreToolUseInput::fake()->edit(ranMigration()))->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'PreToolUse',
            'permissionDecision' => 'ask',
            'permissionDecisionReason' => '0001_01_01_000000_create_users_table has already run on this database. Create a new migration instead of editing it.',
        ],
    ]);
});

it('has no opinion when the database has no migrations table yet', function () {
    Schema::drop('migrations');

    $this->runHook(GuardMigrations::class, PreToolUseInput::fake()->edit(ranMigration()))->assertNoOpinion();
});

it('is registered for Edit and Write, and for Bash', function () {
    $this->assertRegistered(GuardMigrations::class, Event::PreToolUse, matcher: 'Edit|Write');
    $this->assertRegistered(GuardMigrations::class, Event::PreToolUse, matcher: 'Bash');
});

it('is not http-safe, since it reads the database Claude Code\'s environment points at', function () {
    expect(GuardMigrations::httpSafe())->toBeFalse();
});

it('denies a Bash command that edits a migration that already ran in place', function () {
    $this->runHook(GuardMigrations::class, PreToolUseInput::fake()->bash("sed -i 's/name/nickname/' ".ranMigration()))
        ->assertDenied('0001_01_01_000000_create_users_table has already run on this database. Create a new migration instead of editing it.');
});

it('denies a Bash command that writes to a migration that already ran', function (string $command) {
    $input = PreToolUseInput::fake(['cwd' => dirname(database_path())])->bash(str_replace('{ran}', ranMigration(), $command));

    $this->runHook(GuardMigrations::class, $input)
        ->assertDenied('0001_01_01_000000_create_users_table has already run');
})->with([
    'sed --in-place' => ['sed --in-place -e s/a/b/ {ran}'],
    'perl -pi' => ['perl -pi -e s/a/b/ {ran}'],
    'redirection' => ['echo "<?php" > {ran}'],
    'appending redirection' => ['echo "// note" >> {ran}'],
    'redirection without spaces' => ['echo x >{ran}'],
    'tee' => ['echo x | tee -a {ran}'],
    'mv onto it' => ['mv /tmp/new.php {ran}'],
    'mv it away' => ['mv {ran} /tmp/old.php'],
    'cp onto it' => ['cp /tmp/new.php {ran}'],
    'rm' => ['rm -f {ran}'],
    'quoted path' => ['sed -i "s/a/b/" "{ran}"'],
    'after another command' => ['php artisan about && sed -i s/a/b/ {ran}'],
    'relative path' => ['sed -i s/a/b/ database/migrations/0001_01_01_000000_create_users_table.php'],
]);

it('has no opinion on a Bash command that only reads a migration that already ran, or writes one that has not', function (string $command) {
    $input = PreToolUseInput::fake()->bash(str_replace(['{ran}', '{pending}'], [ranMigration(), database_path('migrations/2026_10_20_000000_create_orders_table.php')], $command));

    $this->runHook(GuardMigrations::class, $input)->assertNoOpinion();
})->with([
    'cat' => ['cat {ran}'],
    'grep' => ['grep -n name {ran}'],
    'sed without -i' => ['sed -n 1,10p {ran}'],
    'cp from it' => ['cp {ran} /tmp/backup.php'],
    'redirection elsewhere' => ['cat {ran} > /tmp/copy.php 2>&1'],
    'git' => ['git diff -- {ran}'],
    'sed -i on a pending migration' => ['sed -i s/a/b/ {pending}'],
    'rm of a pending migration' => ['rm {pending}'],
    'no file at all' => ['php artisan migrate'],
]);

it('can be subclassed to change its rule, keeping its Registrations and config', function () {
    enableReadyMadeHook('guard-migrations', ['mode' => 'ask']);

    $this->runHook(GuardEveryMigration::class, PreToolUseInput::fake()->edit(database_path('migrations/2026_10_20_000000_create_orders_table.php')))
        ->assertAsked('2026_10_20_000000_create_orders_table has already run');
    $this->assertRegistered(GuardEveryMigration::class, Event::PreToolUse, matcher: 'Edit|Write');
    $this->assertRegistered(GuardEveryMigration::class, Event::PreToolUse, matcher: 'Bash');
});
