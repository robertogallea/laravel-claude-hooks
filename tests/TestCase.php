<?php

namespace RobertoGallea\ClaudeHooks\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use RobertoGallea\ClaudeHooks\ClaudeHooksServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ClaudeHooksServiceProvider::class];
    }
}
