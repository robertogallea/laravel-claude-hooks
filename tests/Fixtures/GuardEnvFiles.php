<?php

namespace RobertoGallea\ClaudeHooks\Tests\Fixtures;

use RobertoGallea\ClaudeHooks\HookCommand;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;

class GuardEnvFiles extends HookCommand
{
    protected $signature = 'fixture:guard-env-files';

    public function hook(PreToolUseInput $input): ?HookResponse
    {
        if (basename((string) $input->filePath()) !== '.env') {
            return null;
        }

        return $input->deny('.env holds secrets: ask the user to change it.');
    }
}
