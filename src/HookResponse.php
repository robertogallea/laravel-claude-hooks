<?php

namespace RobertoGallea\ClaudeHooks;

use LogicException;

/**
 * What a Hook Command answers to Claude Code. Built only by the helpers of a Hook Input, so it
 * carries only what its Hook Event accepts; the wire format is the translation seam's business.
 */
abstract readonly class HookResponse
{
    /**
     * @param  array<string, mixed>|null  $updatedInput
     * @param  list<array<string, mixed>>|null  $updatedPermissions
     */
    final protected function __construct(
        public Event $event,
        public ?Decision $decision = null,
        public ?string $reason = null,
        public ?array $updatedInput = null,
        public ?array $updatedPermissions = null,
        public bool $interrupt = false,
        public ?string $context = null,
        public mixed $updatedToolOutput = null,
        public ?string $sessionTitle = null,
        public ?string $initialUserMessage = null,
        public ?string $systemMessage = null,
        public ?string $stopReason = null,
    ) {}

    /** @internal Used by Hook Inputs. */
    public static function for(Event $event): static
    {
        return new static($event);
    }

    /**
     * This Hook Response combined with another one for the same Hook Event; the other's fields win where both are set.
     */
    public function and(HookResponse $other): static
    {
        if ($other->event !== $this->event) {
            throw new LogicException("A {$this->event->value} Hook Response cannot be combined with a {$other->event->value} one.");
        }

        $blank = get_object_vars(static::for($this->event));

        return $this->with(...array_filter(get_object_vars($other), fn (mixed $value, string $field) => $value !== $blank[$field], ARRAY_FILTER_USE_BOTH));
    }

    /** @internal */
    public function with(mixed ...$changes): static
    {
        return new static(...[...get_object_vars($this), ...$changes]);
    }
}
