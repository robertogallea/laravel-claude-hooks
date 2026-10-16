<?php

namespace RobertoGallea\ClaudeHooks\Hooks;

use RobertoGallea\ClaudeHooks\Attributes\ClaudeHook;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;

/**
 * Keeps Claude from wiping a database that is not local: a destructive Artisan command (by default
 * `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback` and `db:wipe`), anywhere in a Bash
 * command, is denied (or asked about, in mode "ask") unless the connection it would use is local, with a
 * reason telling Claude to ask the user to run it. The `if` filter boots Laravel only for Artisan commands, and
 * the hook fails closed, since a hook that crashed cannot vouch for the database. Subclass it and override
 * isLocal() or localHosts() to change the rule.
 */
#[ClaudeHook(matcher: 'Bash', if: 'Bash(php artisan *)', failClosed: true)]
class GuardArtisan extends ReadyMadeHook
{
    protected $signature = 'claude-hooks:guard-artisan';

    protected $description = 'PreToolUse hook: deny destructive Artisan commands outside a local database';

    public static function name(): string
    {
        return 'guard-artisan';
    }

    /** It reads the database Claude Code's environment points at, which a running server may not share. */
    public static function httpSafe(): bool
    {
        return false;
    }

    public function hook(PreToolUseInput $input): ?HookResponse
    {
        /** @var string $destructive */
        $destructive = static::setting('destructive', 'migrate:(fresh|refresh|reset|rollback)|db:wipe');

        // The connection is the one named by --database, up to the end of that command, or the default one.
        preg_match_all("~artisan\\s+(?<command>{$destructive})(?![\\w:-])(?<options>[^;&|\\n]*)~", (string) $input->command(), $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            /** @var string $connection */
            $connection = preg_match('/--database[= ]["\']?([\w.-]+)/', $match['options'], $option) === 1 ? $option[1] : config('database.default');

            if (! $this->isLocal($connection)) {
                $reason = "{$match['command']} would run against the '{$connection}' connection, which is not a local database. Ask the user to run it themselves.";

                return static::setting('mode', 'deny') === 'ask' ? $input->ask($reason) : $input->deny($reason);
            }
        }

        return null;
    }

    /**
     * The database hosts that count as local when the app is local.
     *
     * @return list<string>
     */
    protected function localHosts(): array
    {
        /** @var list<string> $hosts */
        $hosts = (array) static::setting('local_hosts', ['127.0.0.1', 'localhost', 'mysql', 'pgsql']);

        return $hosts;
    }

    /**
     * Whether the connection is a local database: SQLite always is; any other driver only when the app is local
     * and the host is one of the local hosts. An unknown connection is not local.
     */
    protected function isLocal(string $connection): bool
    {
        /** @var array<string, mixed> $config */
        $config = (array) config("database.connections.{$connection}", []);

        if (($config['driver'] ?? null) === 'sqlite') {
            return true;
        }

        return $this->laravel->environment('local') && in_array($config['host'] ?? null, $this->localHosts(), true);
    }
}
