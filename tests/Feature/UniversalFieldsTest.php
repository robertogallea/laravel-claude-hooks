<?php

use PHPUnit\Framework\AssertionFailedError;
use RobertoGallea\ClaudeHooks\Inputs\PermissionRequestInput;
use RobertoGallea\ClaudeHooks\Inputs\PostToolBatchInput;
use RobertoGallea\ClaudeHooks\Inputs\PostToolUseFailureInput;
use RobertoGallea\ClaudeHooks\Inputs\PostToolUseInput;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;
use RobertoGallea\ClaudeHooks\Inputs\SessionStartInput;
use RobertoGallea\ClaudeHooks\Inputs\StopInput;
use RobertoGallea\ClaudeHooks\Inputs\SubagentStopInput;
use RobertoGallea\ClaudeHooks\Inputs\UserPromptSubmitInput;

it('shows the user a system message', function () {
    $result = preToolUse(fn (PreToolUseInput $input) => $input->systemMessage('Migrations are guarded in this project.'))
        ->assertSystemMessage('guarded')
        ->assertSystemMessage()
        ->assertNoDecision();

    expect($result->json)->toBe(['systemMessage' => 'Migrations are guarded in this project.']);
});

it('adds a system message to a decision', function () {
    $result = preToolUse(fn (PreToolUseInput $input) => $input->deny('Create a new migration instead.')->withSystemMessage('Blocked an edit to a migration that already ran.'))
        ->assertDenied('new migration')
        ->assertSystemMessage('already ran');

    expect($result->json)->toBe([
        'systemMessage' => 'Blocked an edit to a migration that already ran.',
        'hookSpecificOutput' => [
            'hookEventName' => 'PreToolUse',
            'permissionDecision' => 'deny',
            'permissionDecisionReason' => 'Create a new migration instead.',
        ],
    ]);
});

it('stops the session with a reason', function () {
    $result = preToolUse(fn (PreToolUseInput $input) => $input->stopSession('Build failed, fix errors before continuing'))
        ->assertStoppedSession('Build failed')
        ->assertStoppedSession();

    expect($result->json)->toBe(['continue' => false, 'stopReason' => 'Build failed, fix errors before continuing']);
});

it('stops the session after a decision', function () {
    preToolUse(fn (PreToolUseInput $input) => $input->deny('The database is shared.')->stopSession('Point .env at a local database first.'))
        ->assertDenied('shared')
        ->assertStoppedSession('local database');
});

it('fails the assertions when the field is missing', function () {
    $result = preToolUse(fn (PreToolUseInput $input) => $input->deny('No.'));

    expect(fn () => $result->assertSystemMessage())->toThrow(AssertionFailedError::class)
        ->and(fn () => $result->assertStoppedSession())->toThrow(AssertionFailedError::class);
});

it('shows a system message and stops the session from every event that honours them', function (string $class) {
    $result = answer($class::fake(), fn ($input) => $input->stopSession('Point .env at a local database first.')->withSystemMessage('Stopped by a guard.'))
        ->assertStoppedSession('local database')
        ->assertSystemMessage('guard');

    expect($result->json)->toBe([
        'continue' => false,
        'stopReason' => 'Point .env at a local database first.',
        'systemMessage' => 'Stopped by a guard.',
    ]);
})->with([
    SessionStartInput::class,
    UserPromptSubmitInput::class,
    PreToolUseInput::class,
    PermissionRequestInput::class,
    PostToolUseInput::class,
    PostToolUseFailureInput::class,
    PostToolBatchInput::class,
    StopInput::class,
    SubagentStopInput::class,
]);

describe('the project dir', function () {
    afterEach(fn () => putenv('CLAUDE_PROJECT_DIR'));

    it('is the one Claude Code sets in CLAUDE_PROJECT_DIR', function () {
        putenv('CLAUDE_PROJECT_DIR=/home/user/my-project');
        config(['claude-hooks.project_path' => '/srv/app']);

        expect(seenBy(PreToolUseInput::fake())->projectDir())->toBe('/home/user/my-project');
    });

    it('is the configured project path when Claude Code sets none', function () {
        putenv('CLAUDE_PROJECT_DIR');
        config(['claude-hooks.project_path' => '/srv/app/']);

        expect(seenBy(StopInput::fake())->projectDir())->toBe('/srv/app');
    });

    it('is the application base path when nothing else names it', function () {
        putenv('CLAUDE_PROJECT_DIR');
        config(['claude-hooks.project_path' => null]);

        expect(seenBy(StopInput::fake())->projectDir())->toBe(rtrim(base_path(), '/'));
    });
});
