<?php

namespace RobertoGallea\ClaudeHooks\Hooks;

use Illuminate\Support\Facades\Process;
use RobertoGallea\ClaudeHooks\Attributes\ClaudeHook;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\StopInput;

/**
 * Runs the test suite when Claude is about to stop with PHP files changed, as git sees them from the project
 * path; when git cannot tell (no git, or not a repository), the suite runs anyway. A passing suite is no
 * opinion. A failing one sends Claude back once, with the tail of the output as its next instruction; if the
 * suite still fails when Claude stops again (`stop_hook_active`), Claude may stop and the user is warned, since
 * a failure that needs a human decision cannot be fixed by sending Claude back. It is http-safe: it runs the
 * suite on disk, whichever environment runs it. Subclass it and override testCommand() to change the command.
 */
#[ClaudeHook(timeout: 300)]
class Pest extends ReadyMadeHook
{
    protected $signature = 'claude-hooks:pest';

    protected $description = 'Stop hook: send Claude back once when the test suite fails';

    public static function name(): string
    {
        return 'pest';
    }

    public function hook(StopInput $input): ?HookResponse
    {
        $changes = Process::path($this->projectPath())->run("git status --porcelain -- '*.php'");

        if ($changes->successful() && trim($changes->output()) === '') {
            return null;
        }

        $suite = Process::path($this->projectPath())->forever()->run($this->testCommand());

        if ($suite->successful()) {
            return null;
        }

        if ($input->stopHookActive()) {
            return $input->systemMessage('The test suite is still failing.');
        }

        /** @var int $tail */
        $tail = static::setting('tail', 40);
        $lines = explode("\n", trim($suite->output()."\n".$suite->errorOutput()));

        return $input->block("The test suite is failing. Fix it before you finish:\n".implode("\n", array_slice($lines, -$tail)));
    }

    /** The first word of the test command when it is a path, such as vendor/bin/pest, resolved from the project path. */
    public function requiredBinaries(): array
    {
        $binary = strtok($this->testCommand(), ' ');

        if ($binary === false || ! str_contains($binary, '/')) {
            return [];
        }

        return [str_starts_with($binary, '/') ? $binary : $this->projectPath().'/'.$binary];
    }

    /** The command that runs the test suite. */
    protected function testCommand(): string
    {
        /** @var string $command */
        $command = static::setting('command', 'vendor/bin/pest --compact');

        return $command;
    }

    /** The application's folder, where git and the test suite run. */
    private function projectPath(): string
    {
        return rtrim((string) (config('claude-hooks.project_path') ?? base_path()), '/');
    }
}
