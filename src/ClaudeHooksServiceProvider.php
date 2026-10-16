<?php

namespace RobertoGallea\ClaudeHooks;

use Illuminate\Console\Application as Artisan;
use Illuminate\Support\Facades\Route;
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
        // The http Transport is off unless enabled, and never outside the local environment.
        if (config('claude-hooks.http.enabled') && $this->app->environment('local')) {
            Route::post(Wire\ClaudeCode::HTTP_PATH.'/{signature}', Http\HookController::class)->name('claude-hooks.hook');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/claude-hooks.php' => config_path('claude-hooks.php'),
            ], 'claude-hooks-config');

            $this->commands([Console\InstallCommand::class, Console\DoctorCommand::class]);
        }

        // Read when Artisan starts, so enabling a Ready-made Hook takes effect in the next Artisan process. Also
        // outside the console: the http Transport finds Hook Commands among the Artisan commands.
        Artisan::starting(function (Artisan $artisan): void {
            $artisan->resolveCommands(ReadyMadeHook::enabled());
        });
    }
}
