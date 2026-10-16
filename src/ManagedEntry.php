<?php

namespace RobertoGallea\ClaudeHooks;

/** A Settings Entry this package writes and recognises as its own, identified by its event, matcher and handler. */
final readonly class ManagedEntry
{
    /**
     * @param  string  $signature  the Hook Command its handler runs
     */
    public function __construct(
        public string $event,
        public ?string $matcher,
        public string $signature,
        public ?string $if = null,
    ) {}

    public static function for(Registration $registration): self
    {
        return new self($registration->event->value, $registration->matcher, $registration->signature, $registration->if);
    }

    /** Whether both entries run the same Hook Command on the same Hook Event. */
    public function sameHook(self $other): bool
    {
        return $this->event === $other->event && $this->signature === $other->signature;
    }

    public function describe(): string
    {
        return "{$this->event} {$this->signature}"
            .($this->matcher === null ? '' : " on '{$this->matcher}'")
            .($this->if === null ? '' : " if '{$this->if}'");
    }
}
