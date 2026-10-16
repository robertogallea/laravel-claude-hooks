<?php

namespace RobertoGallea\ClaudeHooks\Inputs;

use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Inputs\Concerns\IsStop;

/** A subagent has finished responding: the hook can keep it working or give it feedback. */
class SubagentStopInput extends HookInput
{
    use IsStop;

    public static function event(): Event
    {
        return Event::SubagentStop;
    }

    /** The subagent's own transcript, nested under the main session's. */
    public function agentTranscriptPath(): ?string
    {
        return $this->get('agent_transcript_path');
    }

    /** @return array<string, mixed> */
    protected static function fakeDefaults(): array
    {
        return [
            'stop_hook_active' => false,
            'agent_id' => 'agent-fake',
            'agent_type' => 'Explore',
            'agent_transcript_path' => sys_get_temp_dir().'/fake-transcript/subagents/agent-fake.jsonl',
            'last_assistant_message' => 'Done.',
            'background_tasks' => [],
            'session_crons' => [],
        ];
    }
}
