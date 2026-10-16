<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ready-made Hooks
    |--------------------------------------------------------------------------
    |
    | Hook Commands shipped by this package. Each one is inactive until it is
    | enabled here, then `php artisan claude-hooks:install` writes it into
    | .claude/settings.json. Every hook takes `matchers`, which maps a matcher it
    | declares to the one to use instead, and `timeout`, in seconds, for all
    | its Registrations; null keeps the declared ones.
    |
    */

    'hooks' => [

        // Denies (mode "ask": asks about) Edit, Write and Bash writes to a migration that already ran on
        // the database Claude Code's environment points at. Paths null means database_path('migrations').
        'guard-migrations' => [
            'enabled' => false,
            'mode' => 'deny',
            'paths' => null,
            'matchers' => ['Edit|Write' => 'Edit|Write', 'Bash' => 'Bash'],
            'timeout' => null,
        ],

        // Denies (mode "ask": asks about) destructive Artisan commands, also inside compound Bash commands,
        // unless the connection they would use is local: SQLite always is, any other database only when the
        // app is local and its host is in local_hosts ('mysql' and 'pgsql' are the Sail service names).
        // destructive is a regex alternation of command names. It fails closed.
        'guard-artisan' => [
            'enabled' => false,
            'mode' => 'deny',
            'destructive' => 'migrate:(fresh|refresh|reset|rollback)|db:wipe',
            'local_hosts' => ['127.0.0.1', 'localhost', 'mysql', 'pgsql'],
            'matchers' => ['Bash' => 'Bash'],
            'timeout' => null,
        ],

        // Tells Claude, at the start of every session (also on resume, clear and compaction), the Laravel
        // and PHP versions, the environment, the default connection and the pending migrations, one line
        // each; set a line to false to leave it out. Paths null means the paths of guard-migrations, then
        // database_path('migrations'). Not http-safe: it describes Claude Code's environment, not a server's.
        'session-context' => [
            'enabled' => false,
            'lines' => [
                'versions' => true,
                'environment' => true,
                'connection' => true,
                'migrations' => true,
            ],
            'paths' => null,
            'timeout' => null,
        ],

        // Runs Pint quietly on each PHP file Claude edits or writes, Blade templates excepted; when Pint
        // fails, Claude is told why, and the edit stands. Binary null means vendor/bin/pint under the
        // project path. Its `if` filters keep Laravel from booting for files that are not PHP.
        'pint' => [
            'enabled' => false,
            'binary' => null,
            'matchers' => ['Edit|Write' => 'Edit|Write'],
            'timeout' => null,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Project Path
    |--------------------------------------------------------------------------
    |
    | The folder whose .claude/settings.json install writes. Null means the
    | application's base path.
    |
    */

    'project_path' => null,

];
