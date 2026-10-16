<?php

namespace RobertoGallea\ClaudeHooks\Responses;

use RobertoGallea\ClaudeHooks\HookResponse;

/** A Hook Response for an event that honours a system message and stopping the session. */
final readonly class FullResponse extends HookResponse
{
    /** Also show the user a warning. */
    public function withSystemMessage(string $message): self
    {
        return $this->with(systemMessage: $message);
    }

    /** Also stop Claude entirely; the reason is shown to the user and stays in the conversation. */
    public function stopSession(string $reason): self
    {
        return $this->with(stopReason: $reason);
    }
}
