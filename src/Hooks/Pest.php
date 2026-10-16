<?php

namespace RobertoGallea\ClaudeHooks\Hooks;

use Dotenv\Parser\Entry;
use Dotenv\Parser\Parser;
use Illuminate\Support\Facades\Process;
use RobertoGallea\ClaudeHooks\Attributes\ClaudeHook;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\StopInput;
use RobertoGallea\ClaudeHooks\ProjectPath;

/**
 * Runs the test suite when Claude is about to stop with PHP files changed, as git sees them from the project
 * path; when git cannot tell (no git, or not a repository), the suite runs anyway. A passing suite is no
 * opinion. A failing one sends Claude back once, with the tail of the output, without terminal colours, as its
 * next instruction; if the suite still fails when Claude stops again (`stop_hook_active`), Claude may stop and
 * the user is warned, since a failure that needs a human decision cannot be fixed by sending Claude back. It is
 * http-safe: it runs the suite on disk, whichever environment runs it. Like `artisan test`, it runs the suite
 * without the variables of the application's .env file, which the hook process loaded, so phpunit.xml can set
 * the testing ones. Subclass it and override testCommand() to change the command.
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
        $changes = Process::path(ProjectPath::configured())->run("git status --porcelain -- '*.php'");

        if ($changes->successful() && trim($changes->output()) === '') {
            return null;
        }

        $suite = Process::path(ProjectPath::configured())->env($this->withoutDotenv())->forever()->run($this->testCommand());

        if ($suite->successful()) {
            return null;
        }

        if ($input->stopHookActive()) {
            return $input->systemMessage('The test suite is still failing.');
        }

        /** @var int $tail */
        $tail = static::setting('tail', 40);
        $output = preg_replace('/\e\[[0-9;?]*[A-Za-z]/', '', $suite->output()."\n".$suite->errorOutput()) ?? '';
        $lines = explode("\n", trim($output));

        return $input->block("The test suite is failing. Fix it before you finish:\n".implode("\n", array_slice($lines, -$tail)));
    }

    /** The first word of the test command when it is a path, such as vendor/bin/pest, resolved from the project path. */
    public function requiredBinaries(): array
    {
        $binary = strtok($this->testCommand(), ' ');

        if ($binary === false || ! str_contains($binary, '/')) {
            return [];
        }

        return [str_starts_with($binary, '/') ? $binary : ProjectPath::configured().'/'.$binary];
    }

    /** The command that runs the test suite. */
    protected function testCommand(): string
    {
        /** @var string $command */
        $command = static::setting('command', 'vendor/bin/pest --compact');

        return $command;
    }

    /**
     * Every variable named in the application's .env file, mapped to false so the suite's process does not inherit
     * it: phpunit.xml does not override a variable that is already set. Without phpdotenv no .env file was loaded.
     *
     * @return array<string, false>
     */
    private function withoutDotenv(): array
    {
        $file = app()->environmentFilePath();

        if (! class_exists(Parser::class) || ! is_file($file)) {
            return [];
        }

        $names = array_map(fn (Entry $entry) => $entry->getName(), (new Parser)->parse((string) file_get_contents($file)));

        return array_fill_keys($names, false);
    }
}
