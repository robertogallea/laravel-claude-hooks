<?php

namespace RobertoGallea\ClaudeHooks\Attributes;

use Attribute;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Transport;

/**
 * Declares how Claude Code should call a Hook Command. Repeat it for several Registrations, for example two
 * `if` filters, since Claude Code has no `||`. The Hook Event is inferred from the handler's type; name it
 * only when the handler takes a union.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class ClaudeHook
{
    /**
     * @param  string|null  $matcher  what the event's matcher filters, such as the tool name; passed through unchanged
     * @param  string|null  $if  one permission rule, such as "Bash(git *)"; only tool events evaluate it
     * @param  int|null  $timeout  seconds before Claude Code cancels the hook
     * @param  string|null  $statusMessage  the spinner message while the hook runs
     * @param  bool  $async  run in the background without blocking (command Transport only)
     * @param  bool  $asyncRewake  run in the background and wake Claude when it fails (command Transport only)
     * @param  bool  $failClosed  block the action when the hook fails, instead of failing open
     */
    public function __construct(
        public ?string $matcher = null,
        public ?string $if = null,
        public ?int $timeout = null,
        public ?string $statusMessage = null,
        public bool $async = false,
        public bool $asyncRewake = false,
        public bool $failClosed = false,
        public Transport $transport = Transport::Command,
        public ?Event $event = null,
    ) {}
}
