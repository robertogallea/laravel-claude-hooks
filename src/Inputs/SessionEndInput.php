<?php

namespace RobertoGallea\ClaudeHooks\Inputs;

use RobertoGallea\ClaudeHooks\Event;

/**
 * The session is ending. The hook cannot block it, and Claude Code discards its output, so this Hook Input has
 * no response helpers: use it for cleanup, within the 1.5 s default budget.
 */
class SessionEndInput extends HookInput
{
    public static function event(): Event
    {
        return Event::SessionEnd;
    }

    /** Why the session ended: "clear", "resume", "logout", "prompt_input_exit" or "other". */
    public function reason(): string
    {
        return (string) $this->get('reason');
    }

    /** @return array<string, mixed> */
    protected static function fakeDefaults(): array
    {
        return ['reason' => 'other'];
    }
}
