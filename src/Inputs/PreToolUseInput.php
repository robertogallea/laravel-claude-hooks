<?php

namespace RobertoGallea\ClaudeHooks\Inputs;

use RobertoGallea\ClaudeHooks\Decision;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Inputs\Concerns\AcceptsUniversalFields;
use RobertoGallea\ClaudeHooks\Inputs\Concerns\HasToolCall;
use RobertoGallea\ClaudeHooks\Responses\FullResponse;

/** Before a tool runs: the hook can allow, deny, ask the user, defer, or change the input. */
class PreToolUseInput extends HookInput
{
    use AcceptsUniversalFields;
    use HasToolCall;

    public static function event(): Event
    {
        return Event::PreToolUse;
    }

    /**
     * Let the tool call run without asking the user, optionally with a changed input.
     *
     * @param  array<string, mixed>|null  $updatedInput  replaces the whole tool input
     */
    public function allow(?string $reason = null, ?array $updatedInput = null): FullResponse
    {
        return $this->respond()->with(decision: Decision::Allow, reason: $reason, updatedInput: $updatedInput);
    }

    /** Refuse the tool call; the reason goes back to Claude, so tell it what to do instead. */
    public function deny(string $reason): FullResponse
    {
        return $this->respond()->with(decision: Decision::Deny, reason: $reason);
    }

    /** Ask the user to confirm the tool call; the reason is shown to the user. */
    public function ask(?string $reason = null): FullResponse
    {
        return $this->respond()->with(decision: Decision::Ask, reason: $reason);
    }

    /** Leave the decision to the rest of the permission flow, with a reason for it. */
    public function defer(?string $reason = null): FullResponse
    {
        return $this->respond()->with(decision: Decision::Defer, reason: $reason);
    }

    /**
     * Run the tool with a changed input, leaving the permission decision to the normal flow.
     *
     * @param  array<string, mixed>  $input  replaces the whole tool input
     */
    public function updateInput(array $input): FullResponse
    {
        return $this->respond()->with(updatedInput: $input);
    }

    /** Add text to Claude's context next to the tool call. */
    public function addContext(string $context): FullResponse
    {
        return $this->respond()->with(context: $context);
    }
}
