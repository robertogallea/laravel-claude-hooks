<?php

namespace RobertoGallea\ClaudeHooks;

/**
 * What a Hook Command answers to Claude Code. Built only by the helpers of a Hook Input, so it
 * carries only what its Hook Event accepts; the wire format is the translation seam's business.
 */
final readonly class HookResponse
{
    /**
     * @param  array<string, mixed>|null  $updatedInput
     */
    private function __construct(
        public Event $event,
        public ?Decision $decision = null,
        public ?string $reason = null,
        public ?array $updatedInput = null,
        public ?string $context = null,
    ) {}

    /** @internal Used by Hook Inputs. */
    public static function for(Event $event): self
    {
        return new self($event);
    }

    /** @internal */
    public function with(mixed ...$changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
