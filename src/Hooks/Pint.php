<?php

namespace RobertoGallea\ClaudeHooks\Hooks;

use Illuminate\Support\Facades\Process;
use RobertoGallea\ClaudeHooks\Attributes\ClaudeHook;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\PostToolUseInput;

/**
 * Formats every PHP file Claude edits or writes with Pint, run quietly on that file from the project path, so
 * that its pint.json applies. Success is no opinion; a failure is told to Claude next to the tool result and
 * never blocks, since the edit already happened. Blade templates are left alone, as Pint leaves them when it
 * scans a project. The `if` filters keep Laravel from booting for files that are not PHP, and the hook checks
 * the extension again, defensively. It is http-safe: it formats a file on disk, whichever environment runs it.
 * Subclass it and override binary() to change the Pint it runs.
 */
#[ClaudeHook(matcher: 'Edit|Write', if: 'Edit(*.php)')]
#[ClaudeHook(matcher: 'Edit|Write', if: 'Write(*.php)')]
class Pint extends ReadyMadeHook
{
    protected $signature = 'claude-hooks:pint';

    protected $description = 'PostToolUse hook: format each PHP file Claude edits or writes with Pint';

    public static function name(): string
    {
        return 'pint';
    }

    public function hook(PostToolUseInput $input): ?HookResponse
    {
        $file = (string) $input->filePath();

        if (! str_ends_with($file, '.php') || str_ends_with($file, '.blade.php')) {
            return null;
        }

        $result = Process::path($this->projectPath())->run([$this->binary(), '--quiet', $file]);

        if ($result->successful()) {
            return null;
        }

        $output = trim($result->errorOutput()."\n".$result->output());

        return $input->addContext("Pint could not format {$file} (exit code {$result->exitCode()})".($output === '' ? '.' : ":\n{$output}"));
    }

    /** The Pint binary: the configured one, else vendor/bin/pint under the project path. */
    protected function binary(): string
    {
        /** @var string|null $binary */
        $binary = static::setting('binary');

        return $binary ?? $this->projectPath().'/vendor/bin/pint';
    }

    /** The application's folder, where Pint finds its pint.json. */
    private function projectPath(): string
    {
        return rtrim((string) (config('claude-hooks.project_path') ?? base_path()), '/');
    }
}
