<?php

namespace RobertoGallea\ClaudeHooks;

use Illuminate\Support\ServiceProvider;

class ClaudeHooksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/claude-hooks.php', 'claude-hooks');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/claude-hooks.php' => config_path('claude-hooks.php'),
            ], 'claude-hooks-config');
        }
    }
}
