<?php

namespace RobertoGallea\ClaudeHooks;

/**
 * The application's folder, as the package's config names it.
 *
 * @internal
 */
final class ProjectPath
{
    /** The configured `claude-hooks.project_path`, else the application's base path, without a trailing slash. */
    public static function configured(): string
    {
        return rtrim((string) (config('claude-hooks.project_path') ?? base_path()), '/');
    }
}
