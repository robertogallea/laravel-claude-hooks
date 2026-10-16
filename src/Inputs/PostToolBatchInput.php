<?php

namespace RobertoGallea\ClaudeHooks\Inputs;

use RobertoGallea\ClaudeHooks\Decision;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Inputs\Concerns\AcceptsUniversalFields;
use RobertoGallea\ClaudeHooks\Responses\FullResponse;

/** After a whole batch of parallel tool calls resolves, before the next model call. */
class PostToolBatchInput extends HookInput
{
    use AcceptsUniversalFields;

    public static function event(): Event
    {
        return Event::PostToolBatch;
    }

    /**
     * Every tool call of the batch, each with `tool_name`, `tool_input`, `tool_use_id` and the serialized `tool_response` Claude sees.
     *
     * @return list<array<string, mixed>>
     */
    public function toolCalls(): array
    {
        return $this->get('tool_calls', []);
    }

    /** Stop the agentic loop before the next model call; the reason is shown and stays in the conversation. */
    public function block(?string $reason = null): FullResponse
    {
        return $this->respond()->with(decision: Decision::Block, reason: $reason);
    }

    /** Add text to Claude's context once, before the next model call. */
    public function addContext(string $context): FullResponse
    {
        return $this->respond()->with(context: $context);
    }

    /** @return array<string, mixed> */
    protected static function fakeDefaults(): array
    {
        return ['tool_calls' => [
            ['tool_name' => 'Bash', 'tool_input' => ['command' => 'true'], 'tool_use_id' => 'toolu_fake', 'tool_response' => ''],
        ]];
    }
}
