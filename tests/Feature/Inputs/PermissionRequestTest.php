<?php

use PHPUnit\Framework\AssertionFailedError;
use RobertoGallea\ClaudeHooks\Inputs\PermissionRequestInput;

it('allows on behalf of the user with a changed input', function () {
    $result = answer(PermissionRequestInput::fake()->bash('npm run lint -- --fix'), fn (PermissionRequestInput $input) => $input->allow(['command' => 'npm run lint']))
        ->assertAllowed()
        ->assertUpdatedInput(['command' => 'npm run lint']);

    expect($result->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'PermissionRequest',
            'decision' => [
                'behavior' => 'allow',
                'updatedInput' => ['command' => 'npm run lint'],
            ],
        ],
    ]);
});

it('allows and applies a suggested permission update', function () {
    $suggestion = [
        'type' => 'addRules',
        'rules' => [['toolName' => 'Bash', 'ruleContent' => 'rm -rf node_modules']],
        'behavior' => 'allow',
        'destination' => 'localSettings',
    ];
    $input = PermissionRequestInput::fake(['permission_suggestions' => [$suggestion]])->bash('rm -rf node_modules');

    $result = answer($input, fn (PermissionRequestInput $input) => $input->allow(updatedPermissions: [$input->permissionSuggestions()[0]]))
        ->assertAllowed()
        ->assertUpdatedPermissions([$suggestion]);

    expect($result->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'PermissionRequest',
            'decision' => [
                'behavior' => 'allow',
                'updatedPermissions' => [[
                    'type' => 'addRules',
                    'rules' => [['toolName' => 'Bash', 'ruleContent' => 'rm -rf node_modules']],
                    'behavior' => 'allow',
                    'destination' => 'localSettings',
                ]],
            ],
        ],
    ]);
});

it('denies with a message for Claude and stops it', function () {
    $result = answer(PermissionRequestInput::fake(), fn (PermissionRequestInput $input) => $input->deny('Ask the user to run this themselves.', interrupt: true))
        ->assertDenied('run this themselves')
        ->assertInterrupted();

    expect($result->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'PermissionRequest',
            'decision' => [
                'behavior' => 'deny',
                'message' => 'Ask the user to run this themselves.',
                'interrupt' => true,
            ],
        ],
    ]);
});

it('denies without a message', function () {
    $result = answer(PermissionRequestInput::fake(), fn (PermissionRequestInput $input) => $input->deny())
        ->assertDenied();

    expect($result->json)->toBe(['hookSpecificOutput' => ['hookEventName' => 'PermissionRequest', 'decision' => ['behavior' => 'deny']]])
        ->and(fn () => $result->assertInterrupted())->toThrow(AssertionFailedError::class)
        ->and(fn () => $result->assertAllowed())->toThrow(AssertionFailedError::class);
});

it('reads the tool call that needs permission', function () {
    $input = seenBy(PermissionRequestInput::fake()->edit('/app/database/migrations/2024_01_01_create_users.php'));

    expect($input)->toBeInstanceOf(PermissionRequestInput::class)
        ->and($input->toolName())->toBe('Edit')
        ->and($input->filePath())->toEndWith('create_users.php')
        ->and($input->toolUseId())->toBeNull()
        ->and($input->permissionSuggestions())->toBe([]);
});
