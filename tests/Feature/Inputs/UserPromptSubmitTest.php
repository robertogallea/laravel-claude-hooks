<?php

use PHPUnit\Framework\AssertionFailedError;
use RobertoGallea\ClaudeHooks\Inputs\UserPromptSubmitInput;

it('blocks a prompt, adds context and names the session in one Hook Response', function () {
    $result = answer(
        UserPromptSubmitInput::fake(),
        fn (UserPromptSubmitInput $input) => $input->block('Explanation for decision')
            ->and($input->addContext('My additional context here'))
            ->and($input->setSessionTitle('My session title')),
    )->assertBlocked('Explanation')
        ->assertContextContains('additional context')
        ->assertSessionTitle('My session title');

    expect($result->json)->toBe([
        'decision' => 'block',
        'reason' => 'Explanation for decision',
        'hookSpecificOutput' => [
            'hookEventName' => 'UserPromptSubmit',
            'additionalContext' => 'My additional context here',
            'sessionTitle' => 'My session title',
        ],
    ]);
});

it('adds context alongside the prompt', function () {
    answer(UserPromptSubmitInput::fake(), fn (UserPromptSubmitInput $input) => $input->addContext('The default connection is sqlite.'))
        ->assertNoDecision()
        ->assertContextContains('sqlite');
});

it('names the session', function () {
    $result = answer(UserPromptSubmitInput::fake(), fn (UserPromptSubmitInput $input) => $input->setSessionTitle('factorial'))
        ->assertSessionTitle();

    expect($result->json)->toBe(['hookSpecificOutput' => ['hookEventName' => 'UserPromptSubmit', 'sessionTitle' => 'factorial']]);
});

it('reads the prompt and the session title', function () {
    $input = seenBy(UserPromptSubmitInput::fake(['prompt' => 'Write a function to calculate the factorial of a number', 'session_title' => 'maths']));

    expect($input)->toBeInstanceOf(UserPromptSubmitInput::class)
        ->and($input->prompt())->toBe('Write a function to calculate the factorial of a number')
        ->and($input->sessionTitle())->toBe('maths');
});

it('fails the session title assertion when the hook names no session', function () {
    $result = answer(UserPromptSubmitInput::fake(), fn (UserPromptSubmitInput $input) => $input->addContext('x'));

    expect(fn () => $result->assertSessionTitle())->toThrow(AssertionFailedError::class);
});
