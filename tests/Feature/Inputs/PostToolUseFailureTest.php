<?php

use RobertoGallea\ClaudeHooks\Inputs\PostToolUseFailureInput;

it('adds context about the failure', function () {
    $result = answer(PostToolUseFailureInput::fake(), fn (PostToolUseFailureInput $input) => $input->addContext('Additional information about the failure for Claude'))
        ->assertContextContains('about the failure')
        ->assertNoDecision();

    expect($result->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'PostToolUseFailure',
            'additionalContext' => 'Additional information about the failure for Claude',
        ],
    ]);
});

it('reads the failed tool call', function () {
    $input = seenBy(PostToolUseFailureInput::fake([
        'error' => "Exit code 1\nError: Cannot find module 'express'",
        'is_interrupt' => false,
        'duration_ms' => 4187,
    ])->bash('npm test'));

    expect($input)->toBeInstanceOf(PostToolUseFailureInput::class)
        ->and($input->command())->toBe('npm test')
        ->and($input->error())->toStartWith('Exit code 1')
        ->and($input->isInterrupt())->toBeFalse()
        ->and($input->durationMs())->toBe(4187);
});
