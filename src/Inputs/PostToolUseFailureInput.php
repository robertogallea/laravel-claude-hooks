<?php

namespace RobertoGallea\ClaudeHooks\Inputs;

use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Inputs\Concerns\AcceptsUniversalFields;
use RobertoGallea\ClaudeHooks\Inputs\Concerns\HasToolCall;
use RobertoGallea\ClaudeHooks\Responses\FullResponse;

/** After a tool call fails: the hook can give Claude corrective context. */
class PostToolUseFailureInput extends HookInput
{
    use AcceptsUniversalFields;
    use HasToolCall {
        fakeDefaults as toolCallFakeDefaults;
    }

    public static function event(): Event
    {
        return Event::PostToolUseFailure;
    }

    /** What went wrong; for Bash the first line is `Exit code N`, the rest is display text. */
    public function error(): string
    {
        return (string) $this->get('error');
    }

    /** Whether the failure was an abort rather than an error the tool reported. */
    public function isInterrupt(): bool
    {
        return (bool) $this->get('is_interrupt', false);
    }

    /** How long the tool ran, in milliseconds. */
    public function durationMs(): ?int
    {
        return $this->get('duration_ms');
    }

    /** Add text to Claude's context next to the error. */
    public function addContext(string $context): FullResponse
    {
        return $this->respond()->with(context: $context);
    }

    /** @return array<string, mixed> */
    protected static function fakeDefaults(): array
    {
        return [...self::toolCallFakeDefaults(), 'error' => 'Exit code 1', 'is_interrupt' => false, 'duration_ms' => 12];
    }
}
