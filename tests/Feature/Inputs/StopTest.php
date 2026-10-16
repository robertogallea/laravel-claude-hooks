<?php

use PHPUnit\Framework\AssertionFailedError;
use RobertoGallea\ClaudeHooks\Inputs\StopInput;

it('sends Claude back to work with a reason', function () {
    $result = answer(StopInput::fake(), fn (StopInput $input) => $input->block('Must be provided when Claude is blocked from stopping'))
        ->assertBlocked('blocked from stopping')
        ->assertBlocked();

    expect($result->json)->toBe([
        'decision' => 'block',
        'reason' => 'Must be provided when Claude is blocked from stopping',
    ]);
});

it('gives Claude non-error feedback that continues the conversation', function () {
    $result = answer(StopInput::fake(), fn (StopInput $input) => $input->addContext('Please run the test suite before finishing'))
        ->assertContextContains('test suite')
        ->assertNoDecision();

    expect($result->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'Stop',
            'additionalContext' => 'Please run the test suite before finishing',
        ],
    ]);
});

it('warns the user without blocking', function () {
    answer(StopInput::fake(), fn (StopInput $input) => $input->systemMessage('The test suite is still failing.'))
        ->assertSystemMessage('still failing')
        ->assertNoDecision();
});

it('reads the stop fields', function () {
    $input = seenBy(StopInput::fake([
        'stop_hook_active' => true,
        'last_assistant_message' => "I've completed the refactoring. Here's a summary...",
        'background_tasks' => [['id' => 'task-001', 'type' => 'shell', 'status' => 'running', 'description' => 'tail logs', 'command' => 'tail -f /var/log/syslog']],
        'session_crons' => [['id' => 'cron-001', 'schedule' => '0 9 * * 1-5', 'recurring' => true, 'prompt' => 'check the build']],
    ]));

    expect($input)->toBeInstanceOf(StopInput::class)
        ->and($input->stopHookActive())->toBeTrue()
        ->and($input->lastAssistantMessage())->toStartWith("I've completed")
        ->and($input->backgroundTasks()[0]['command'])->toBe('tail -f /var/log/syslog')
        ->and($input->sessionCrons()[0]['schedule'])->toBe('0 9 * * 1-5');
});

it('fakes a first stop', function () {
    $input = seenBy(StopInput::fake());

    expect($input->stopHookActive())->toBeFalse()
        ->and($input->backgroundTasks())->toBe([])
        ->and($input->eventName())->toBe('Stop');
});

it('fails the blocked assertion when the hook lets Claude stop', function () {
    $result = answer(StopInput::fake(), fn (StopInput $input) => $input->addContext('Fine.'));

    expect(fn () => $result->assertBlocked())->toThrow(AssertionFailedError::class);
});
