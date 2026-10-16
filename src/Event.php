<?php

namespace RobertoGallea\ClaudeHooks;

/** A point of a Claude Code session at which a Hook fires, named exactly as Claude Code names it. */
enum Event: string
{
    case PreToolUse = 'PreToolUse';

    /**
     * The typed Hook Input for this event.
     *
     * @return class-string<Inputs\HookInput>
     */
    public function inputClass(): string
    {
        return match ($this) {
            self::PreToolUse => Inputs\PreToolUseInput::class,
        };
    }
}
