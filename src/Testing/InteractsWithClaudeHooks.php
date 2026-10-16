<?php

namespace RobertoGallea\ClaudeHooks\Testing;

use Illuminate\Contracts\Console\Kernel;
use RobertoGallea\ClaudeHooks\HookCommand;
use RobertoGallea\ClaudeHooks\Inputs\HookInput;
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
        $kernel = $this->app->make(Kernel::class);
        $hook = $this->app->make($command);
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
}
