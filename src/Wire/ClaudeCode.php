<?php

namespace RobertoGallea\ClaudeHooks\Wire;

use InvalidArgumentException;
use RobertoGallea\ClaudeHooks\Decision;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\GenericInput;
use RobertoGallea\ClaudeHooks\Inputs\HookInput;
use RobertoGallea\ClaudeHooks\Responses\BareResponse;

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

        $class = Event::tryFrom((string) $name)?->inputClass() ?? GenericInput::class;

        if (! in_array($class, $accepted, true)) {
            $class = in_array(GenericInput::class, $accepted, true) ? GenericInput::class : null;
        }

        if ($class === null) {
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
        $specific = $this->withoutNulls(match ($response->event) {
            Event::PreToolUse => [
                'permissionDecision' => $response->decision?->value,
                'permissionDecisionReason' => $response->reason,
                'updatedInput' => $response->updatedInput,
            ],
            Event::PermissionRequest => [
                'decision' => $response->decision === null ? null : $this->withoutNulls([
                    'behavior' => $response->decision->value,
                    'updatedInput' => $response->updatedInput,
                    'updatedPermissions' => $response->updatedPermissions,
                    'message' => $response->reason,
                    'interrupt' => $response->interrupt ?: null,
                ]),
            ],
            default => [],
        } + [
            'additionalContext' => $response->context,
            'sessionTitle' => $response->sessionTitle,
            'initialUserMessage' => $response->initialUserMessage,
            'updatedToolOutput' => $response->updatedToolOutput,
        ]);

        $universal = $response instanceof BareResponse ? [] : $this->withoutNulls([
            'continue' => $response->stopReason === null ? null : false,
            'stopReason' => $response->stopReason,
            'systemMessage' => $response->systemMessage,
        ]);

        return $universal + ($response->decision === Decision::Block ? $this->withoutNulls([
            'decision' => 'block',
            'reason' => $response->reason,
        ]) : []) + ($specific === [] ? [] : [
            'hookSpecificOutput' => ['hookEventName' => $response->event->value, ...$specific],
        ]);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function withoutNulls(array $fields): array
    {
        return array_filter($fields, fn (mixed $value) => $value !== null);
    }
}
