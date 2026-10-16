<?php

use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;
use RobertoGallea\ClaudeHooks\Tests\Fixtures\GuardEnvFiles;
use RobertoGallea\ClaudeHooks\Tests\Fixtures\ScriptedPreToolUse;

function preToolUse(Closure $answer, ?PreToolUseInput $input = null)
{
    ScriptedPreToolUse::$answer = $answer;

    return test()->runHook(ScriptedPreToolUse::class, $input ?? PreToolUseInput::fake()->bash('ls'));
}

it('denies a tool call with a reason', function () {
    $this->runHook(GuardEnvFiles::class, PreToolUseInput::fake()->edit(base_path('.env')))
        ->assertDenied('holds secrets');
});

it('answers a denial in the format of the hooks reference', function () {
    expect(preToolUse(fn (PreToolUseInput $input) => $input->deny('Database writes are not allowed'))->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'PreToolUse',
            'permissionDecision' => 'deny',
            'permissionDecisionReason' => 'Database writes are not allowed',
        ],
    ]);
});

it('answers nothing when the hook has no opinion', function () {
    $this->runHook(GuardEnvFiles::class, PreToolUseInput::fake()->edit(base_path('app/User.php')))
        ->assertNoOpinion();
});

it('allows, asks or defers, with an optional reason', function () {
    preToolUse(fn (PreToolUseInput $input) => $input->allow('Read-only command'))->assertAllowed('Read-only');
    preToolUse(fn (PreToolUseInput $input) => $input->ask())->assertAsked();
    preToolUse(fn (PreToolUseInput $input) => $input->defer('Ask the classifier'))->assertDeferred('classifier');

    expect(preToolUse(fn (PreToolUseInput $input) => $input->ask())->json)->toBe([
        'hookSpecificOutput' => ['hookEventName' => 'PreToolUse', 'permissionDecision' => 'ask'],
    ]);
});

it('changes the input of a tool call', function () {
    $result = preToolUse(fn (PreToolUseInput $input) => $input->updateInput(['command' => 'ls -la']))
        ->assertUpdatedInput(['command' => 'ls -la']);

    expect($result->json)->toBe([
        'hookSpecificOutput' => ['hookEventName' => 'PreToolUse', 'updatedInput' => ['command' => 'ls -la']],
    ]);
});

it('allows a changed input', function () {
    preToolUse(fn (PreToolUseInput $input) => $input->allow(updatedInput: ['command' => 'ls -la']))
        ->assertAllowed()
        ->assertUpdatedInput(['command' => 'ls -la']);
});

it('adds context for Claude', function () {
    preToolUse(fn (PreToolUseInput $input) => $input->addContext('The tests use SQLite.'))
        ->assertNoDecision()
        ->assertContextContains('SQLite');
});

it('reads the tool call', function () {
    $seen = null;
    preToolUse(function (PreToolUseInput $input) use (&$seen) {
        $seen = [$input->toolName(), $input->filePath(), $input->toolInput('old_string'), $input->toolUseId(), $input->sessionId(), $input->permissionMode()];

        return null;
    }, PreToolUseInput::fake(['permission_mode' => 'plan'])->edit('/app/routes/web.php', 'a', 'b'));

    expect($seen)->toBe(['Edit', '/app/routes/web.php', 'a', 'toolu_fake', 'fake-session', 'plan']);
});

it('reads a Bash command', function () {
    $seen = null;
    preToolUse(function (PreToolUseInput $input) use (&$seen) {
        $seen = $input->command();

        return null;
    }, PreToolUseInput::fake()->bash('php artisan migrate'));

    expect($seen)->toBe('php artisan migrate');
});
