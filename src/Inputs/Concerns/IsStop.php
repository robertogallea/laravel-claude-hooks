<?php

namespace RobertoGallea\ClaudeHooks\Inputs\Concerns;

use RobertoGallea\ClaudeHooks\Decision;
use RobertoGallea\ClaudeHooks\Responses\FullResponse;

/** Fields and helpers shared by Stop and SubagentStop: the agent has finished responding. */
trait IsStop
{
    use AcceptsUniversalFields;

    /** Whether Claude is already continuing because a stop hook blocked; check it to avoid looping. */
    public function stopHookActive(): bool
    {
        return (bool) $this->get('stop_hook_active', false);
    }

    /** The text of the agent's final response. */
    public function lastAssistantMessage(): ?string
    {
        return $this->get('last_assistant_message');
    }

    /**
     * Background tasks still in flight in the session.
     *
     * @return list<array<string, mixed>>
     */
    public function backgroundTasks(): array
    {
        return $this->get('background_tasks', []);
    }

    /**
     * Scheduled wakeups of the session.
     *
     * @return list<array<string, mixed>>
     */
    public function sessionCrons(): array
    {
        return $this->get('session_crons', []);
    }

    /** Keep the agent working; the reason is its next instruction. */
    public function block(string $reason): FullResponse
    {
        return $this->respond()->with(decision: Decision::Block, reason: $reason);
    }

    /** Give the agent non-error feedback; the conversation continues so it can act on it. */
    public function addContext(string $context): FullResponse
    {
        return $this->respond()->with(context: $context);
    }
}
