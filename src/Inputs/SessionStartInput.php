<?php

namespace RobertoGallea\ClaudeHooks\Inputs;

use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Inputs\Concerns\AcceptsUniversalFields;
use RobertoGallea\ClaudeHooks\Responses\FullResponse;

/** A session begins or resumes: the hook can give Claude context, name the session, or supply its first message. */
class SessionStartInput extends HookInput
{
    use AcceptsUniversalFields;

    public static function event(): Event
    {
        return Event::SessionStart;
    }

    /** How the session started: "startup", "resume", "clear", "compact" or "fork". */
    public function source(): string
    {
        return (string) $this->get('source');
    }

    /** The active model, when Claude Code includes it. */
    public function model(): ?string
    {
        return $this->get('model');
    }

    /** The session's custom title, when one is set; check it before setting one. */
    public function sessionTitle(): ?string
    {
        return $this->get('session_title');
    }

    /** Add text to Claude's context at the start of the conversation, before the first prompt. */
    public function addContext(string $context): FullResponse
    {
        return $this->respond()->with(context: $context);
    }

    /** Set the session title, as /rename does; applies when the source is "startup", "resume" or "fork". */
    public function setSessionTitle(string $title): FullResponse
    {
        return $this->respond()->with(sessionTitle: $title);
    }

    /** Use this as the first user message of the session, in non-interactive (-p) mode. */
    public function setInitialUserMessage(string $message): FullResponse
    {
        return $this->respond()->with(initialUserMessage: $message);
    }

    /** @return array<string, mixed> */
    protected static function fakeDefaults(): array
    {
        return ['source' => 'startup', 'model' => 'claude-opus-5'];
    }
}
