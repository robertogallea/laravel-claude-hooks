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
}
