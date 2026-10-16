<?php

use RobertoGallea\ClaudeHooks\Inputs\SubagentStopInput;

it('keeps the subagent working with a reason', function () {
    $result = answer(SubagentStopInput::fake(), fn (SubagentStopInput $input) => $input->block('Also check the migrations folder.'))
        ->assertBlocked('migrations');

    expect($result->json)->toBe(['decision' => 'block', 'reason' => 'Also check the migrations folder.']);
});

it('gives the subagent feedback', function () {
    expect(answer(SubagentStopInput::fake(), fn (SubagentStopInput $input) => $input->addContext('Summarise in one line.'))->json)->toBe([
        'hookSpecificOutput' => ['hookEventName' => 'SubagentStop', 'additionalContext' => 'Summarise in one line.'],
    ]);
});

it('reads the subagent fields', function () {
    $input = seenBy(SubagentStopInput::fake([
        'stop_hook_active' => false,
        'agent_id' => 'def456',
        'agent_type' => 'Explore',
        'agent_transcript_path' => '~/.claude/projects/.../abc123/subagents/agent-def456.jsonl',
        'last_assistant_message' => 'Analysis complete. Found 3 potential issues...',
    ]));

    expect($input)->toBeInstanceOf(SubagentStopInput::class)
        ->and($input->agentId())->toBe('def456')
        ->and($input->agentType())->toBe('Explore')
        ->and($input->agentTranscriptPath())->toEndWith('agent-def456.jsonl')
        ->and($input->lastAssistantMessage())->toStartWith('Analysis complete')
        ->and($input->stopHookActive())->toBeFalse();
});
