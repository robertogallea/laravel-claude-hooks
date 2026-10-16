<?php

use RobertoGallea\ClaudeHooks\Inputs\PostToolUseInput;

it('blocks with a reason next to the tool result', function () {
    $result = answer(PostToolUseInput::fake()->edit('/app/app/User.php'), fn (PostToolUseInput $input) => $input->block('Pint found style issues.'))
        ->assertBlocked('Pint');

    expect($result->json)->toBe(['decision' => 'block', 'reason' => 'Pint found style issues.']);
});

it('adds context next to the tool result', function () {
    expect(answer(PostToolUseInput::fake(), fn (PostToolUseInput $input) => $input->addContext('This file is generated. Edit src/schema.ts and run `bun generate` instead.'))->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'PostToolUse',
            'additionalContext' => 'This file is generated. Edit src/schema.ts and run `bun generate` instead.',
        ],
    ]);
});

it('replaces the tool output and adds context in one Hook Response', function () {
    $redacted = ['stdout' => '[redacted]', 'stderr' => '', 'interrupted' => false, 'isImage' => false];

    $result = answer(
        PostToolUseInput::fake()->bash('cat .env'),
        fn (PostToolUseInput $input) => $input->replaceOutput($redacted)->and($input->addContext('Additional information for Claude')),
    )->assertUpdatedOutput($redacted)->assertContextContains('Additional information');

    expect($result->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'PostToolUse',
            'additionalContext' => 'Additional information for Claude',
            'updatedToolOutput' => $redacted,
        ],
    ]);
});

it('reads the tool call and its response', function () {
    $input = seenBy(PostToolUseInput::fake([
        'tool_response' => ['filePath' => '/path/to/file.txt', 'type' => 'create'],
        'duration_ms' => 12,
    ])->write('/path/to/file.txt', 'file content'));

    expect($input)->toBeInstanceOf(PostToolUseInput::class)
        ->and($input->toolName())->toBe('Write')
        ->and($input->filePath())->toBe('/path/to/file.txt')
        ->and($input->toolResponse('type'))->toBe('create')
        ->and($input->toolResponse())->toBe(['filePath' => '/path/to/file.txt', 'type' => 'create'])
        ->and($input->durationMs())->toBe(12)
        ->and($input->toolUseId())->toBe('toolu_fake');
});
