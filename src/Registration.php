<?php

namespace RobertoGallea\ClaudeHooks;

/**
 * A Hook Command's Registration with its Hook Event resolved: everything its Settings Entry is derived from.
 */
final readonly class Registration
{
    /**
     * @param  class-string<HookCommand>  $command
     * @param  string  $signature  the Artisan command name Claude Code runs
     * @param  bool  $httpSafe  whether the Hook Command gives the same answer with the server's environment
     */
    public function __construct(
        public string $command,
        public string $signature,
        public Event $event,
        public ?string $matcher,
        public ?string $if,
        public ?int $timeout,
        public ?string $statusMessage,
        public bool $async,
        public bool $asyncRewake,
        public bool $failClosed,
        public Transport $transport,
        public bool $httpSafe,
    ) {}

    /** This Registration on the http Transport. */
    public function overHttp(): self
    {
        return new self(
            $this->command, $this->signature, $this->event, $this->matcher, $this->if, $this->timeout,
            $this->statusMessage, $this->async, $this->asyncRewake, $this->failClosed, Transport::Http, $this->httpSafe,
        );
    }
}
