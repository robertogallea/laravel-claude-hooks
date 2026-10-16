<?php

namespace RobertoGallea\ClaudeHooks;

/** A point of a Claude Code session at which a Hook fires, named exactly as Claude Code names it. */
enum Event: string
{
    case SessionStart = 'SessionStart';
    case Setup = 'Setup';
    case UserPromptSubmit = 'UserPromptSubmit';
    case UserPromptExpansion = 'UserPromptExpansion';
    case PreToolUse = 'PreToolUse';
    case PermissionRequest = 'PermissionRequest';
    case PermissionDenied = 'PermissionDenied';
    case PostToolUse = 'PostToolUse';
    case PostToolUseFailure = 'PostToolUseFailure';
    case PostToolBatch = 'PostToolBatch';
    case Notification = 'Notification';
    case MessageDisplay = 'MessageDisplay';
    case SubagentStart = 'SubagentStart';
    case SubagentStop = 'SubagentStop';
    case TaskCreated = 'TaskCreated';
    case TaskCompleted = 'TaskCompleted';
    case Stop = 'Stop';
    case StopFailure = 'StopFailure';
    case TeammateIdle = 'TeammateIdle';
    case InstructionsLoaded = 'InstructionsLoaded';
    case ConfigChange = 'ConfigChange';
    case CwdChanged = 'CwdChanged';
    case DirectoryAdded = 'DirectoryAdded';
    case FileChanged = 'FileChanged';
    case WorktreeCreate = 'WorktreeCreate';
    case WorktreeRemove = 'WorktreeRemove';
    case PreCompact = 'PreCompact';
    case PostCompact = 'PostCompact';
    case PreModelSwitch = 'PreModelSwitch';
    case PostModelSwitch = 'PostModelSwitch';
    case Elicitation = 'Elicitation';
    case ElicitationResult = 'ElicitationResult';
    case SessionEnd = 'SessionEnd';

    /**
     * The Hook Input for this event: a typed one, or the generic one for events the package does not model.
     *
     * @return class-string<Inputs\HookInput>
     */
    public function inputClass(): string
    {
        return match ($this) {
            self::SessionStart => Inputs\SessionStartInput::class,
            self::UserPromptSubmit => Inputs\UserPromptSubmitInput::class,
            self::PreToolUse => Inputs\PreToolUseInput::class,
            self::PermissionRequest => Inputs\PermissionRequestInput::class,
            self::PostToolUse => Inputs\PostToolUseInput::class,
            self::PostToolUseFailure => Inputs\PostToolUseFailureInput::class,
            self::PostToolBatch => Inputs\PostToolBatchInput::class,
            self::Notification => Inputs\NotificationInput::class,
            self::SubagentStop => Inputs\SubagentStopInput::class,
            self::Stop => Inputs\StopInput::class,
            self::PreCompact => Inputs\PreCompactInput::class,
            self::SessionEnd => Inputs\SessionEndInput::class,
            default => Inputs\GenericInput::class,
        };
    }

    /**
     * Whether Claude Code filters this event by matcher; on the others a matcher is silently ignored.
     */
    public function takesMatcher(): bool
    {
        return ! in_array($this, [
            self::UserPromptSubmit, self::PostToolBatch, self::Stop, self::TeammateIdle, self::TaskCreated,
            self::TaskCompleted, self::WorktreeCreate, self::WorktreeRemove, self::MessageDisplay, self::CwdChanged,
        ], true);
    }

    /**
     * Whether Claude Code evaluates a handler's `if` filter on this event; on the others a handler with `if` never runs.
     */
    public function evaluatesIf(): bool
    {
        return in_array($this, self::toolEvents(), true);
    }

    /**
     * Whether a failing hook on this event can block it with `onFailure: "block"`. On `Stop`, `SubagentStop`,
     * `TaskCompleted` and `TeammateIdle`, blocking sends Claude back to work, so Claude Code ignores the field.
     */
    public function honoursOnFailure(): bool
    {
        return ! in_array($this, [self::Stop, self::SubagentStop, self::TaskCompleted, self::TeammateIdle], true);
    }

    /**
     * Whether a hook on this event decides whether an action happens: a tool call, a permission, a prompt. When such
     * a hook cannot be reached and fails open, the action goes ahead unchecked.
     */
    public function guardsAnAction(): bool
    {
        return in_array($this, [self::PreToolUse, self::PermissionRequest, self::UserPromptSubmit], true);
    }

    /**
     * The tool events, the only ones that evaluate `if`.
     *
     * @return list<self>
     */
    public static function toolEvents(): array
    {
        return [self::PreToolUse, self::PostToolUse, self::PostToolUseFailure, self::PermissionRequest, self::PermissionDenied];
    }
}
