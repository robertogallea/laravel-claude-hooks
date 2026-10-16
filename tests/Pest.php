<?php

use RobertoGallea\ClaudeHooks\Inputs\HookInput;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;
use RobertoGallea\ClaudeHooks\Testing\HookResult;
use RobertoGallea\ClaudeHooks\Tests\Fixtures\ScriptedHook;
use RobertoGallea\ClaudeHooks\Tests\Fixtures\ScriptedPreToolUse;
use RobertoGallea\ClaudeHooks\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/** Run a PreToolUse Hook Command that answers with the given closure. */
function preToolUse(Closure $answer, ?PreToolUseInput $input = null)
{
    ScriptedPreToolUse::$answer = $answer;

    return test()->runHook(ScriptedPreToolUse::class, $input ?? PreToolUseInput::fake()->bash('ls'));
}

/** Run a Hook Command that accepts every Hook Input and answers with the given closure. */
function answer(HookInput $input, Closure $answer): HookResult
{
    ScriptedHook::$answer = $answer;

    return test()->runHook(ScriptedHook::class, $input);
}

/** The Hook Input a Hook Command that accepts every Hook Input receives. */
function seenBy(HookInput $input): HookInput
{
    $seen = null;
    answer($input, function (HookInput $input) use (&$seen) {
        $seen = $input;

        return null;
    })->assertNoOpinion();

    return $seen;
}
