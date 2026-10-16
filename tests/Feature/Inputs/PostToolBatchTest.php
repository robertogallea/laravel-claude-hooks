<?php

use RobertoGallea\ClaudeHooks\Inputs\PostToolBatchInput;

it('adds context once for the whole batch', function () {
    expect(answer(PostToolBatchInput::fake(), fn (PostToolBatchInput $input) => $input->addContext('These files are part of the ledger module. Run pytest before marking the task complete.'))->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'PostToolBatch',
            'additionalContext' => 'These files are part of the ledger module. Run pytest before marking the task complete.',
        ],
    ]);
});

it('stops the agentic loop with a reason', function () {
    $result = answer(PostToolBatchInput::fake(), fn (PostToolBatchInput $input) => $input->block('Two migrations were edited at once.'))
        ->assertBlocked('migrations');

    expect($result->json)->toBe(['decision' => 'block', 'reason' => 'Two migrations were edited at once.']);
});

it('reads every tool call of the batch', function () {
    $input = seenBy(PostToolBatchInput::fake(['tool_calls' => [
        ['tool_name' => 'Read', 'tool_input' => ['file_path' => '/ledger/accounts.py'], 'tool_use_id' => 'toolu_01', 'tool_response' => '1\tfrom __future__ import annotations'],
        ['tool_name' => 'Read', 'tool_input' => ['file_path' => '/ledger/transactions.py'], 'tool_use_id' => 'toolu_02', 'tool_response' => '1\tfrom __future__ import annotations'],
    ]]));

    expect($input)->toBeInstanceOf(PostToolBatchInput::class)
        ->and($input->toolCalls())->toHaveCount(2)
        ->and($input->toolCalls()[1]['tool_input']['file_path'])->toBe('/ledger/transactions.py');
});
