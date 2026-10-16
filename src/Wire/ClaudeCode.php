<?php

namespace RobertoGallea\ClaudeHooks\Wire;

use InvalidArgumentException;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\HookInput;

/**
 * The translation seam: the only place that knows Claude Code's payload and response JSON.
 *
 * @internal
 */
final class ClaudeCode
{
    /**
     * The Hook Input for a payload, among the Hook Input classes a handler accepts.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<class-string<HookInput>>  $accepted
     */
    public function decode(array $payload, array $accepted): HookInput
    {
        $name = $payload['hook_event_name'] ?? null;

        if ($name === null && count($accepted) === 1) {
            return new $accepted[0]($payload);
        }

        $class = Event::tryFrom((string) $name)?->inputClass();

        if ($class === null || ! in_array($class, $accepted, true)) {
            throw new InvalidArgumentException("This hook does not handle the '{$name}' event.");
        }

        return new $class($payload);
    }

    /**
     * The JSON object Claude Code expects for a Hook Response.
     *
     * @return array<string, mixed>
     */
    public function encode(HookResponse $response): array
    {
        $specific = array_filter([
            'permissionDecision' => $response->decision?->value,
            'permissionDecisionReason' => $response->reason,
            'updatedInput' => $response->updatedInput,
            'additionalContext' => $response->context,
        ], fn (mixed $value) => $value !== null);

        return $specific === [] ? [] : [
            'hookSpecificOutput' => ['hookEventName' => $response->event->value, ...$specific],
        ];
    }
}
