<?php

use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\PreCompactInput;

it('blocks compaction', function () {
    $result = answer(PreCompactInput::fake(), fn (PreCompactInput $input) => $input->block('Save the plan to a file first.'))
        ->assertBlocked('plan');

    expect($result->json)->toBe(['decision' => 'block', 'reason' => 'Save the plan to a file first.']);
});

it('reads what triggered compaction', function () {
    $input = seenBy(PreCompactInput::fake(['trigger' => 'manual', 'custom_instructions' => 'Keep the migration list']));

    expect($input)->toBeInstanceOf(PreCompactInput::class)
        ->and($input->trigger())->toBe('manual')
        ->and($input->customInstructions())->toBe('Keep the migration list');
});

it('fakes an automatic compaction', function () {
    $input = seenBy(PreCompactInput::fake());

    expect($input->trigger())->toBe('auto')
        ->and($input->customInstructions())->toBeNull();
});

it('offers no system message or session stop, which Claude Code discards for PreCompact', function () {
    $response = PreCompactInput::fake()->block('x');

    expect($response)->toBeInstanceOf(HookResponse::class)
        ->and(method_exists($response, 'withSystemMessage'))->toBeFalse()
        ->and(method_exists($response, 'stopSession'))->toBeFalse()
        ->and(method_exists(PreCompactInput::class, 'systemMessage'))->toBeFalse()
        ->and(method_exists(PreCompactInput::class, 'stopSession'))->toBeFalse();
});
