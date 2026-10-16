<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ready-made Hooks
    |--------------------------------------------------------------------------
    |
    | Hook Commands shipped by this package. Each one is inactive until it is
    | enabled here, then `php artisan claude-hooks:install` writes it into
    | .claude/settings.json.
    |
    */

    'hooks' => [],

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
