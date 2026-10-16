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
