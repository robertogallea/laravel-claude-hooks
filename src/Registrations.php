<?php

namespace RobertoGallea\ClaudeHooks;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use ReflectionClass;
use RobertoGallea\ClaudeHooks\Attributes\ClaudeHook;
use RobertoGallea\ClaudeHooks\Inputs\GenericInput;

/** Derives Registrations from Hook Commands. */
final class Registrations
{
    public function __construct(private readonly Application $app) {}

    /**
     * Every registered Artisan command that is a Hook Command.
     *
     * @return list<class-string<HookCommand>>
     */
    public function commands(): array
    {
        $commands = array_filter($this->app->make(Kernel::class)->all(), fn ($command) => $command instanceof HookCommand);

        return array_values(array_unique(array_map(fn (HookCommand $command) => $command::class, $commands)));
    }

    /**
     * The Registrations of every discovered Hook Command.
     *
     * @return list<Registration>
     *
     * @throws InvalidRegistration
     */
    public function all(): array
    {
        return array_merge(...array_map($this->of(...), $this->commands()));
    }

    /**
     * The Registrations a Hook Command declares, with their Hook Events resolved.
     *
     * @param  class-string<HookCommand>  $command
     * @return list<Registration>
     *
     * @throws InvalidRegistration
     */
    public function of(string $command): array
    {
        $declared = array_map(
            fn ($attribute) => $attribute->newInstance(),
            (new ReflectionClass($command))->getAttributes(ClaudeHook::class),
        ) ?: [new ClaudeHook];

        /** @var HookCommand $instance */
        $instance = $this->app->make($command);

        return array_map(fn (ClaudeHook $declared) => $this->validated(new Registration(
            command: $command,
            signature: (string) $instance->getName(),
            event: $this->event($command, $declared),
            matcher: $declared->matcher,
            if: $declared->if,
            timeout: $declared->timeout,
            statusMessage: $declared->statusMessage,
            async: $declared->async,
            asyncRewake: $declared->asyncRewake,
            failClosed: $declared->failClosed,
            transport: $declared->transport,
            httpSafe: $command::httpSafe(),
        )), $declared);
    }

    /**
     * The declared event, checked against the handler's type, or the one inferred from it.
     *
     * @param  class-string<HookCommand>  $command
     */
    private function event(string $command, ClaudeHook $declared): Event
    {
        $accepted = $command::inputClasses();

        if ($declared->event !== null) {
            if (! in_array($declared->event->inputClass(), $accepted, true) && ! in_array(GenericInput::class, $accepted, true)) {
                throw new InvalidRegistration("{$command} is registered for {$declared->event->value}, but its hook() does not accept the {$declared->event->value} Hook Input ({$declared->event->inputClass()}).");
            }

            return $declared->event;
        }

        $event = count($accepted) === 1 ? $accepted[0]::event() : null;

        return $event ?? throw new InvalidRegistration(count($accepted) > 1
            ? "{$command}::hook() takes a union of Hook Inputs, so each Registration must name its event, as in #[ClaudeHook(event: Event::{$accepted[0]::event()?->value})]."
            : "{$command}::hook() takes the generic Hook Input, so each Registration must name its event.");
    }

    private function validated(Registration $registration): Registration
    {
        $command = $registration->command;
        $event = $registration->event->value;

        if ($registration->if !== null && ! $registration->event->evaluatesIf()) {
            $tools = array_map(fn (Event $event) => $event->value, Event::toolEvents());
            $last = array_pop($tools);

            throw new InvalidRegistration("{$command} sets an `if` filter on {$event}, which Claude Code evaluates only on ".implode(', ', $tools)." and {$last}: the hook would never run.");
        }

        if ($registration->matcher !== null && ! $registration->event->takesMatcher()) {
            throw new InvalidRegistration("{$command} sets a matcher on {$event}, which takes none: Claude Code would ignore it.");
        }

        if ($registration->failClosed) {
            if (! $registration->event->honoursOnFailure()) {
                throw new InvalidRegistration("{$command} fails closed on {$event}, where Claude Code ignores onFailure: blocking would send Claude back to work on a hook it cannot repair.");
            }

            foreach (['async' => $registration->async, 'asyncRewake' => $registration->asyncRewake] as $field => $set) {
                if ($set) {
                    throw new InvalidRegistration("{$command} fails closed with {$field}, but Claude Code ignores onFailure on background hooks: run it in the foreground or let it fail open.");
                }
            }
        }

        if ($registration->transport === Transport::Http) {
            foreach (['async' => $registration->async, 'asyncRewake' => $registration->asyncRewake] as $field => $set) {
                if ($set) {
                    throw new InvalidRegistration("{$command} sets {$field} on the http Transport, which runs only in the foreground: use the command Transport.");
                }
            }

            if (! $registration->httpSafe) {
                throw new InvalidRegistration("{$command} is not http-safe: its answer depends on Claude Code's environment, so it needs the command Transport.");
            }
        }

        return $registration;
    }
}
