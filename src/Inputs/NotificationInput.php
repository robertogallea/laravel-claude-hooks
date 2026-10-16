<?php

namespace RobertoGallea\ClaudeHooks\Inputs;

use RobertoGallea\ClaudeHooks\Event;

/**
 * Claude Code sent a notification. The hook cannot block or change it, and Claude Code discards its system
 * message and `continue`, so this Hook Input has no response helpers: use it for side effects.
 */
class NotificationInput extends HookInput
{
    public static function event(): Event
    {
        return Event::Notification;
    }

    /** The notification text. */
    public function message(): string
    {
        return (string) $this->get('message');
    }

    public function title(): ?string
    {
        return $this->get('title');
    }

    /** Which notification fired, such as "permission_prompt" or "idle_prompt". */
    public function notificationType(): string
    {
        return (string) $this->get('notification_type');
    }

    /** @return array<string, mixed> */
    protected static function fakeDefaults(): array
    {
        return ['message' => 'Claude is waiting for your input', 'title' => 'Claude Code', 'notification_type' => 'idle_prompt'];
    }
}
