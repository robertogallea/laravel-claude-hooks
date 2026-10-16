<?php

namespace RobertoGallea\ClaudeHooks\Testing;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use LogicException;
use PHPUnit\Framework\Assert;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\HookCommand;
use RobertoGallea\ClaudeHooks\Inputs\HookInput;
use RobertoGallea\ClaudeHooks\Registration;
use RobertoGallea\ClaudeHooks\Registrations;
use Symfony\Component\Console\Output\BufferedOutput;

/** Run Hook Commands in tests, in-process, through the Artisan kernel. */
trait InteractsWithClaudeHooks
{
    /**
     * Run a Hook Command with a Hook Input, as Claude Code would.
     *
     * @param  class-string<HookCommand>  $command
     */
    public function runHook(string $command, HookInput $input): HookResult
    {
        $app = $this->app ?? throw new LogicException('runHook() needs the application: call it from inside a test.');
        /** @var ConsoleKernel $kernel */
        $kernel = $app->make(Kernel::class);
        $hook = $app->make($command);
        $kernel->registerCommand($hook);

        $file = tempnam(sys_get_temp_dir(), 'claude-hook-');
        file_put_contents($file, json_encode($input->payload, JSON_THROW_ON_ERROR));

        try {
            $exitCode = $kernel->call((string) $hook->getName(), ['--payload' => $file], $output = new BufferedOutput);
        } finally {
            unlink($file);
        }

        return new HookResult($output->fetch(), $exitCode);
    }

    /**
     * Assert that a Hook Command has a Registration for the event with exactly this matcher and `if` filter;
     * null means none.
     *
     * @param  class-string<HookCommand>  $command
     */
    public function assertRegistered(string $command, Event $event, ?string $matcher = null, ?string $if = null): void
    {
        $app = $this->app ?? throw new LogicException('assertRegistered() needs the application: call it from inside a test.');
        $registrations = $app->make(Registrations::class)->of($command);
        $found = array_filter($registrations, fn (Registration $registration) => $registration->event === $event
            && $registration->matcher === $matcher
            && $registration->if === $if);

        $wanted = $event->value.($matcher === null ? '' : " on '{$matcher}'").($if === null ? '' : " if '{$if}'");
        $actual = array_map(fn (Registration $registration) => $registration->event->value
            .($registration->matcher === null ? '' : " on '{$registration->matcher}'")
            .($registration->if === null ? '' : " if '{$registration->if}'"), $registrations);

        Assert::assertNotEmpty($found, "{$command} is not registered for {$wanted}. Its Registrations:\n  ".implode("\n  ", $actual));
    }
}
