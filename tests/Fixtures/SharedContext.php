<?php

namespace RobertoGallea\ClaudeHooks\Tests\Fixtures;

use RobertoGallea\ClaudeHooks\HookCommand;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\SessionStartInput;
use RobertoGallea\ClaudeHooks\Inputs\UserPromptSubmitInput;

/** Gives Claude the same context at session start and with every prompt. */
class SharedContext extends HookCommand
{
    protected $signature = 'fixture:shared-context';

    public function hook(SessionStartInput|UserPromptSubmitInput $input): ?HookResponse
    {
        return $input->addContext('Answered as '.$input::class.' for '.$input->eventName());
    }
}
