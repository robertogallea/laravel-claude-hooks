<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use RobertoGallea\ClaudeHooks\HookCommand;
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

/**
 * Enable a Ready-made Hook with this config, then start Artisan again, as a fresh process with that config would.
 *
 * @param  array<string, mixed>  $config
 */
function enableReadyMadeHook(string $name, array $config = []): void
{
    config(["claude-hooks.hooks.{$name}" => ['enabled' => true, ...$config]]);
    app(Kernel::class)->setArtisan(null);
}

/** @param  class-string<HookCommand>  ...$commands */
function hookCommands(string ...$commands): void
{
    foreach ($commands as $command) {
        app(Kernel::class)->registerCommand(app($command));
    }
}

function settings(string $file = 'settings.json'): string
{
    return file_get_contents(test()->project."/.claude/{$file}");
}

function givenSettings(string $json, string $file = 'settings.json'): void
{
    File::ensureDirectoryExists(test()->project.'/.claude');
    file_put_contents(test()->project."/.claude/{$file}", $json);
}
