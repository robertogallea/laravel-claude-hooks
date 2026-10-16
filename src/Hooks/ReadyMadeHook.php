<?php

namespace RobertoGallea\ClaudeHooks\Hooks;

use RobertoGallea\ClaudeHooks\Attributes\ClaudeHook;
use RobertoGallea\ClaudeHooks\HookCommand;

/**
 * A Hook Command shipped by this package, inactive until `claude-hooks.hooks.<name>.enabled` is true.
 * Its settings live under that config key, and subclasses keep reading it.
 */
abstract class ReadyMadeHook extends HookCommand
{
    /** Every Ready-made Hook. */
    private const HOOKS = [
        GuardMigrations::class,
        GuardArtisan::class,
    ];

    /** The name of this hook's config, under `claude-hooks.hooks`. */
    abstract public static function name(): string;

    /**
     * The Ready-made Hooks enabled in config.
     *
     * @return list<class-string<ReadyMadeHook>>
     */
    public static function enabled(): array
    {
        $enabled = [];

        foreach (self::HOOKS as $hook) {
            if ($hook::setting('enabled', false)) {
                $enabled[] = $hook;
            }
        }

        return $enabled;
    }

    /**
     * A declared Registration with the overrides in config: `matchers` maps a declared matcher to the one to
     * use instead, and `timeout` applies to every Registration of the hook.
     */
    public static function configured(ClaudeHook $declared): ClaudeHook
    {
        /** @var array<string, string> $matchers */
        $matchers = (array) static::setting('matchers', []);
        /** @var int|null $timeout */
        $timeout = static::setting('timeout');

        return new ClaudeHook(
            matcher: $declared->matcher === null ? null : ($matchers[$declared->matcher] ?? $declared->matcher),
            if: $declared->if,
            timeout: $timeout ?? $declared->timeout,
            statusMessage: $declared->statusMessage,
            async: $declared->async,
            asyncRewake: $declared->asyncRewake,
            failClosed: $declared->failClosed,
            transport: $declared->transport,
            event: $declared->event,
        );
    }

    /** One of this hook's config settings. */
    protected static function setting(string $key, mixed $default = null): mixed
    {
        return config('claude-hooks.hooks.'.static::name().".{$key}", $default);
    }
}
