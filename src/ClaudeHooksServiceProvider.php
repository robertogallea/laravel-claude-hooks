<?php

namespace RobertoGallea\ClaudeHooks;

use Illuminate\Console\Application as Artisan;
use Illuminate\Support\ServiceProvider;
use RobertoGallea\ClaudeHooks\Hooks\ReadyMadeHook;

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

            $this->commands([Console\InstallCommand::class, Console\DoctorCommand::class]);

            // Read when Artisan starts, so enabling a Ready-made Hook takes effect in the next Artisan process.
            Artisan::starting(function (Artisan $artisan): void {
                $artisan->resolveCommands(ReadyMadeHook::enabled());
            });
        }
    }
}
