<?php

namespace RobertoGallea\ClaudeHooks\Inputs;

use RobertoGallea\ClaudeHooks\Decision;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Inputs\Concerns\AcceptsUniversalFields;
use RobertoGallea\ClaudeHooks\Inputs\Concerns\HasToolCall;
use RobertoGallea\ClaudeHooks\Responses\FullResponse;

/**
 * Claude Code is about to ask the user for permission to use a tool: the hook can answer on the user's behalf.
 * Deny and ask rules are still evaluated, so allowing here does not override a matching deny rule.
 */
class PermissionRequestInput extends HookInput
{
    use AcceptsUniversalFields;
    use HasToolCall;

    public static function event(): Event
    {
        return Event::PermissionRequest;
    }

    /**
     * The permission updates Claude Code suggests for this request, such as an allow rule; echo one back to apply it.
     *
     * @return list<array<string, mixed>>
     */
    public function permissionSuggestions(): array
    {
        return $this->get('permission_suggestions', []);
    }

    /**
     * Grant the permission, optionally with a changed input or permission updates to apply.
     *
     * @param  array<string, mixed>|null  $updatedInput  replaces the whole tool input; it is re-evaluated against deny and ask rules
     * @param  list<array<string, mixed>>|null  $updatedPermissions  permission update entries, such as one of permissionSuggestions()
     */
    public function allow(?array $updatedInput = null, ?array $updatedPermissions = null): FullResponse
    {
        return $this->respond()->with(decision: Decision::Allow, updatedInput: $updatedInput, updatedPermissions: $updatedPermissions);
    }

    /** Deny the permission; the message tells Claude why, and interrupt stops Claude. */
    public function deny(?string $message = null, bool $interrupt = false): FullResponse
    {
        return $this->respond()->with(decision: Decision::Deny, reason: $message, interrupt: $interrupt);
    }

    /** @return array<string, mixed> */
    protected static function fakeDefaults(): array
    {
        return ['tool_name' => 'Bash', 'tool_input' => ['command' => 'true'], 'permission_suggestions' => []];
    }
}
