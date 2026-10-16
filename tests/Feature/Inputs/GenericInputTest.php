<?php

use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Inputs\GenericInput;

it('lists every Hook Event of the hooks reference', function () {
    expect(array_map(fn (Event $event) => $event->value, Event::cases()))->toEqualCanonicalizing([
        'SessionStart', 'Setup', 'UserPromptSubmit', 'UserPromptExpansion', 'PreToolUse', 'PermissionRequest',
        'PermissionDenied', 'PostToolUse', 'PostToolUseFailure', 'PostToolBatch', 'Notification', 'MessageDisplay',
        'SubagentStart', 'SubagentStop', 'TaskCreated', 'TaskCompleted', 'Stop', 'StopFailure', 'TeammateIdle',
        'InstructionsLoaded', 'ConfigChange', 'CwdChanged', 'DirectoryAdded', 'FileChanged', 'WorktreeCreate',
        'WorktreeRemove', 'PreCompact', 'PostCompact', 'PreModelSwitch', 'PostModelSwitch', 'Elicitation',
        'ElicitationResult', 'SessionEnd',
    ]);
});

it('gives an event the package does not model to a generic Hook Input', function () {
    $input = seenBy(GenericInput::fakeFor('ConfigChange', ['source' => 'project_settings', 'file_path' => '/home/user/my-project/.claude/settings.json']));

    expect($input)->toBeInstanceOf(GenericInput::class)
        ->and($input->eventName())->toBe('ConfigChange')
        ->and($input->get('source'))->toBe('project_settings')
        ->and($input->sessionId())->toBe('fake-session');
});

it('gives an unknown event name to a generic Hook Input with dot-path access', function () {
    $input = seenBy(GenericInput::fakeFor('SomethingFromTheFuture', ['details' => ['level' => 3]]));

    expect($input)->toBeInstanceOf(GenericInput::class)
        ->and($input->eventName())->toBe('SomethingFromTheFuture')
        ->and($input->get('details.level'))->toBe(3)
        ->and($input->get('missing.path', 'default'))->toBe('default');
});
