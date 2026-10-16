<?php

namespace RobertoGallea\ClaudeHooks\Hooks;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\QueryException;
use PDOException;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\SessionStartInput;

/**
 * Gives Claude a snapshot of the application at the start of every session, including resume, clear and
 * compaction: the Laravel and PHP versions, the environment, the default connection and the pending migrations,
 * one line each, any of which config can turn off. Subclass it and override migrationPaths() to change where
 * migrations are looked for.
 */
class SessionContext extends ReadyMadeHook
{
    protected $signature = 'claude-hooks:session-context';

    protected $description = 'SessionStart hook: tell Claude about this application';

    public static function name(): string
    {
        return 'session-context';
    }

    /** It reads the environment and database Claude Code's environment points at, which a running server may not share. */
    public static function httpSafe(): bool
    {
        return false;
    }

    public function hook(SessionStartInput $input): ?HookResponse
    {
        $lines = [
            'versions' => fn () => 'Laravel '.$this->laravel->version().' on PHP '.PHP_VERSION.'.',
            'environment' => fn () => 'Environment: '.$this->laravel->environment().'.',
            'connection' => fn () => "Default database connection: '".config('database.default')."'.",
            'migrations' => $this->pendingMigrations(...),
        ];

        $context = [];

        foreach ($lines as $line => $text) {
            if (static::setting("lines.{$line}", true)) {
                $context[] = $text();
            }
        }

        return $context === [] ? null : $input->addContext(implode("\n", $context));
    }

    /**
     * The folders migrations are loaded from: this hook's paths, else those of guard-migrations, else
     * database_path('migrations').
     *
     * @return list<string>
     */
    protected function migrationPaths(): array
    {
        /** @var list<string> $paths */
        $paths = (array) (static::setting('paths') ?? GuardMigrations::setting('paths') ?? [database_path('migrations')]);

        return $paths;
    }

    /**
     * The line on pending migrations: all of them, when the database has no migrations table yet, and why they
     * could not be read, when the database cannot be reached, so that the other lines still reach Claude. It reads
     * them from the default connection, the one the line before names: the migrator's repository keeps the last
     * connection a migrate command ran on, which need not be it.
     */
    private function pendingMigrations(): string
    {
        /** @var Migrator $migrator */
        $migrator = $this->laravel->make('migrator');
        $files = array_keys($migrator->getMigrationFiles($this->migrationPaths()));

        try {
            $ran = $migrator->usingConnection(
                config('database.default'),
                fn () => $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [],
            );
        } catch (QueryException|PDOException $e) {
            return 'Could not read migrations: '.$this->shortMessage($e).'.';
        }

        $pending = array_values(array_diff($files, $ran));

        return $pending === [] ? 'All migrations have run.' : 'Pending migrations: '.implode(', ', $pending).'.';
    }

    /** The driver's message, without the SQLSTATE codes, the connection details and the query Laravel adds. */
    private function shortMessage(QueryException|PDOException $e): string
    {
        $message = ($e instanceof QueryException ? $e->getPrevious() : null)?->getMessage() ?? $e->getMessage();
        $message = (string) preg_replace('/^SQLSTATE\[\w+\](\s*\[\w+\])?\s*/', '', $message);
        $message = (string) preg_replace('/\s*\(Connection: .*\)$/s', '', $message);

        return rtrim($message, '. ');
    }
}
