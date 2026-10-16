<?php

namespace RobertoGallea\ClaudeHooks\Tests\Fixtures;

use Illuminate\Support\ServiceProvider;

/** Registers the fixture Hook Commands for the Testbench binary, which the subprocess smoke tests run. */
class FixturesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->commands([
            BrokenHook::class,
            GuardEnvFiles::class,
        ]);
    }
}
