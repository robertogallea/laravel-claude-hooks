<?php

namespace RobertoGallea\ClaudeHooks\Inputs;

use RobertoGallea\ClaudeHooks\Decision;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Inputs\Concerns\AcceptsUniversalFields;
use RobertoGallea\ClaudeHooks\Inputs\Concerns\HasToolCall;
use RobertoGallea\ClaudeHooks\Responses\FullResponse;

/** After a tool call succeeds: the hook can give Claude feedback or replace what Claude sees of the result. */
class PostToolUseInput extends HookInput
{
    use AcceptsUniversalFields;
    use HasToolCall {
        fakeDefaults as toolCallFakeDefaults;
    }

    public static function event(): Event
    {
        return Event::PostToolUse;
    }

    /** What the tool returned, or one of its fields by dot path; its shape depends on the tool. */
    public function toolResponse(?string $path = null, mixed $default = null): mixed
    {
        return $path === null ? $this->get('tool_response') : $this->get("tool_response.{$path}", $default);
    }

    /** How long the tool ran, in milliseconds. */
    public function durationMs(): ?int
    {
        return $this->get('duration_ms');
    }

    /** Add the reason next to the tool result; Claude still sees the original output. */
    public function block(?string $reason = null): FullResponse
    {
        return $this->respond()->with(decision: Decision::Block, reason: $reason);
    }

    /** Add text to Claude's context next to the tool result. */
    public function addContext(string $context): FullResponse
    {
        return $this->respond()->with(context: $context);
    }

    /**
     * Replace what Claude sees of the tool's output. The tool has already run; the value must match the tool's output shape.
     *
     * @param  array<string, mixed>|string  $output
     */
    public function replaceOutput(array|string $output): FullResponse
    {
        return $this->respond()->with(updatedToolOutput: $output);
    }

    /** @return array<string, mixed> */
    protected static function fakeDefaults(): array
    {
        return [
            ...self::toolCallFakeDefaults(),
            'tool_response' => ['stdout' => '', 'stderr' => '', 'interrupted' => false, 'isImage' => false],
            'duration_ms' => 12,
        ];
    }
}
