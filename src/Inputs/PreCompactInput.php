<?php

namespace RobertoGallea\ClaudeHooks\Inputs;

use RobertoGallea\ClaudeHooks\Decision;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Responses\BareResponse;

/**
 * Claude Code is about to compact the context: the hook can block it. Claude Code discards a PreCompact hook's
 * system message and `continue`, so this Hook Input offers neither.
 */
class PreCompactInput extends HookInput
{
    public static function event(): Event
    {
        return Event::PreCompact;
    }

    /** "manual" for /compact, "auto" for automatic or idle compaction. */
    public function trigger(): string
    {
        return (string) $this->get('trigger');
    }

    /** What the user passed to /compact; null for automatic compaction or when they passed nothing. */
    public function customInstructions(): ?string
    {
        return $this->get('custom_instructions');
    }

    /** Skip compaction; for a manual /compact the reason is shown to the user. */
    public function block(?string $reason = null): BareResponse
    {
        return BareResponse::for(self::event())->with(decision: Decision::Block, reason: $reason);
    }

    /** @return array<string, mixed> */
    protected static function fakeDefaults(): array
    {
        return ['trigger' => 'auto', 'custom_instructions' => null];
    }
}
