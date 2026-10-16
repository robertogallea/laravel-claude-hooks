<?php

namespace RobertoGallea\ClaudeHooks\Tests\Fixtures;

use Closure;
use LogicException;
use RobertoGallea\ClaudeHooks\HookCommand;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\GenericInput;
use RobertoGallea\ClaudeHooks\Inputs\NotificationInput;
use RobertoGallea\ClaudeHooks\Inputs\PermissionRequestInput;
use RobertoGallea\ClaudeHooks\Inputs\PostToolBatchInput;
use RobertoGallea\ClaudeHooks\Inputs\PostToolUseFailureInput;
use RobertoGallea\ClaudeHooks\Inputs\PostToolUseInput;
use RobertoGallea\ClaudeHooks\Inputs\PreCompactInput;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;
use RobertoGallea\ClaudeHooks\Inputs\SessionEndInput;
use RobertoGallea\ClaudeHooks\Inputs\SessionStartInput;
use RobertoGallea\ClaudeHooks\Inputs\StopInput;
use RobertoGallea\ClaudeHooks\Inputs\SubagentStopInput;
use RobertoGallea\ClaudeHooks\Inputs\UserPromptSubmitInput;

/** Answers any Hook Event with whatever the test scripted. */
class ScriptedHook extends HookCommand
{
    protected $signature = 'fixture:scripted-hook';

    /** @var (Closure(mixed): ?HookResponse)|null */
    public static ?Closure $answer = null;

    public function hook(
        SessionStartInput|UserPromptSubmitInput|PreToolUseInput|PermissionRequestInput|PostToolUseInput|PostToolUseFailureInput|PostToolBatchInput|NotificationInput|SubagentStopInput|StopInput|PreCompactInput|SessionEndInput|GenericInput $input,
    ): ?HookResponse {
        $answer = self::$answer ?? throw new LogicException('Script an answer before running the hook.');

        return $answer($input);
    }
}
