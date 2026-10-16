<?php

namespace RobertoGallea\ClaudeHooks\Tests\Fixtures;

use Closure;
use LogicException;
use RobertoGallea\ClaudeHooks\HookCommand;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;

/** Answers with whatever the test scripted. */
class ScriptedPreToolUse extends HookCommand
{
    protected $signature = 'fixture:scripted-pre-tool-use';

    /** @var (Closure(PreToolUseInput): ?HookResponse)|null */
    public static ?Closure $answer = null;

    public function hook(PreToolUseInput $input): ?HookResponse
    {
        $answer = self::$answer ?? throw new LogicException('Script an answer before running the hook.');

        return $answer($input);
    }
}
