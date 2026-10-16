<?php

namespace RobertoGallea\ClaudeHooks\Tests\Fixtures;

use RobertoGallea\ClaudeHooks\HookCommand;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;
use RuntimeException;

/** A Hook Command whose handler fails, as one would when its database is unreachable. */
class BrokenHook extends HookCommand
{
    protected $signature = 'fixture:broken-hook';

    public function hook(PreToolUseInput $input): ?HookResponse
    {
        throw new RuntimeException('The database is down.');
    }
}
