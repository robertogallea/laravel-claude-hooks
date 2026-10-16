<?php

namespace RobertoGallea\ClaudeHooks\Inputs;

use RobertoGallea\ClaudeHooks\Decision;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Inputs\Concerns\AcceptsUniversalFields;
use RobertoGallea\ClaudeHooks\Responses\FullResponse;

/** A prompt was submitted, before Claude processes it: the hook can block it, add context, or name the session. */
class UserPromptSubmitInput extends HookInput
{
    use AcceptsUniversalFields;

    public static function event(): Event
    {
        return Event::UserPromptSubmit;
    }

    /** The submitted text, with pasted content expanded in place. */
    public function prompt(): string
    {
        return (string) $this->get('prompt');
    }

    /** The session's custom title, when one is set. */
    public function sessionTitle(): ?string
    {
        return $this->get('session_title');
    }

    /** Stop the prompt before it reaches Claude; the reason is shown to the user, not added to context. */
    public function block(?string $reason = null): FullResponse
    {
        return $this->respond()->with(decision: Decision::Block, reason: $reason);
    }

    /** Add text to Claude's context alongside the prompt. */
    public function addContext(string $context): FullResponse
    {
        return $this->respond()->with(context: $context);
    }

    /** Set the session title, as /rename does. */
    public function setSessionTitle(string $title): FullResponse
    {
        return $this->respond()->with(sessionTitle: $title);
    }

    /** @return array<string, mixed> */
    protected static function fakeDefaults(): array
    {
        return ['prompt' => 'Add a test for the User model'];
    }
}
