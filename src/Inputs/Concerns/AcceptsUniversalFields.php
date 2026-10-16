<?php

namespace RobertoGallea\ClaudeHooks\Inputs\Concerns;

use RobertoGallea\ClaudeHooks\Responses\FullResponse;

/** Helpers for events that honour a system message and stopping the session. */
trait AcceptsUniversalFields
{
    /** Show the user a warning, with no other opinion. */
    public function systemMessage(string $message): FullResponse
    {
        return $this->respond()->withSystemMessage($message);
    }

    /** Stop Claude entirely; the reason is shown to the user and stays in the conversation. */
    public function stopSession(string $reason): FullResponse
    {
        return $this->respond()->stopSession($reason);
    }

    protected function respond(): FullResponse
    {
        return FullResponse::for(static::event());
    }
}
