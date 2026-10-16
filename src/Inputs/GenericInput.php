<?php

namespace RobertoGallea\ClaudeHooks\Inputs;

use RobertoGallea\ClaudeHooks\Event;

/**
 * Any Hook Event the package does not model, including names added by later Claude Code releases.
 * Read its fields by dot path with get(); it has no response helpers, because what the event honours is unknown.
 */
class GenericInput extends HookInput
{
    public static function event(): ?Event
    {
        return null;
    }

    /**
     * A generic Hook Input for the named event, with realistic common fields, for tests.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function fakeFor(string $event, array $overrides = []): static
    {
        return static::fake(['hook_event_name' => $event, ...$overrides]);
    }
}
