<?php

namespace RobertoGallea\ClaudeHooks\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use RobertoGallea\ClaudeHooks\ClaudeHooksServiceProvider;
use RobertoGallea\ClaudeHooks\Testing\InteractsWithClaudeHooks;

abstract class TestCase extends Orchestra
{
    use InteractsWithClaudeHooks;

    protected function getPackageProviders($app): array
    {
        return [ClaudeHooksServiceProvider::class];
    }
}
