<?php

namespace RobertoGallea\ClaudeHooks\Inputs;

use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Inputs\Concerns\IsStop;

/** Claude has finished responding: the hook can send it back to work or give it feedback. */
class StopInput extends HookInput
{
    use IsStop;

    public static function event(): Event
    {
        return Event::Stop;
    }

    /** @return array<string, mixed> */
    protected static function fakeDefaults(): array
    {
        return [
            'stop_hook_active' => false,
            'last_assistant_message' => 'Done.',
            'background_tasks' => [],
            'session_crons' => [],
        ];
    }
}
