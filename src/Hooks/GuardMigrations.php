<?php

namespace RobertoGallea\ClaudeHooks\Hooks;

use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use RobertoGallea\ClaudeHooks\Attributes\ClaudeHook;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;

/**
 * Keeps Claude from rewriting schema history: an Edit, Write or Bash write to a migration that already ran on the
 * current database is denied (or asked about, in mode "ask"), with a reason telling Claude to create a new
 * migration. Subclass it and override hasRun(), migrationPaths() or writtenBy() to change the rule.
 */
#[ClaudeHook(matcher: 'Edit|Write')]
#[ClaudeHook(matcher: 'Bash')]
class GuardMigrations extends ReadyMadeHook
{
    protected $signature = 'claude-hooks:guard-migrations';

    protected $description = 'PreToolUse hook: deny edits to migrations that already ran';

    public static function name(): string
    {
        return 'guard-migrations';
    }

    /** It reads the database Claude Code's environment points at, which a running server may not share. */
    public static function httpSafe(): bool
    {
        return false;
    }

    public function hook(PreToolUseInput $input): ?HookResponse
    {
        $paths = $input->toolName() === 'Bash' ? $this->writtenBy((string) $input->command()) : [(string) $input->filePath()];
        $migration = null;

        foreach ($paths as $path) {
            $candidate = $this->migration($this->absolute($path, $input));

            if ($candidate !== null && $this->hasRun($candidate)) {
                $migration = $candidate;
                break;
            }
        }

        if ($migration === null) {
            return null;
        }

        $reason = "{$migration} has already run on this database. Create a new migration instead of editing it.";

        return static::setting('mode', 'deny') === 'ask' ? $input->ask($reason) : $input->deny($reason);
    }

    /**
     * The folders migrations are loaded from.
     *
     * @return list<string>
     */
    protected function migrationPaths(): array
    {
        /** @var list<string> $paths */
        $paths = (array) (static::setting('paths') ?? [database_path('migrations')]);

        return $paths;
    }

    /** Whether the migration is recorded in the migrations table of the current database. */
    protected function hasRun(string $migration): bool
    {
        /** @var MigrationRepositoryInterface $repository */
        $repository = $this->laravel->make('migration.repository');

        return $repository->repositoryExists() && in_array($migration, $repository->getRan(), true);
    }

    /**
     * The files a Bash command may write to. A seatbelt, not a shell parser: it splits the command on `;`, `&&`,
     * `||`, `|` and newlines outside quotes, and in each part takes the targets of `>` and `>>`, every file argument of an
     * in-place `sed` or `perl` (`-i`, `--in-place`), `tee`, `rm` and `mv` (moving a migration away rewrites
     * history too), and the destination of `cp`. Scripts, `xargs`, subshells and the like go around it.
     *
     * @return list<string>
     */
    protected function writtenBy(string $command): array
    {
        $written = [];

        foreach (preg_split('/("[^"]*"|\'[^\']*\')(*SKIP)(*FAIL)|&&|\|\||[;|\n]/', $command) ?: [] as $part) {
            $words = [];
            $tokens = $this->words($part);

            for ($i = 0; $i < count($tokens); $i++) {
                if (preg_match('/^\d*>>?(.*)$/', $tokens[$i], $redirection) === 1) {
                    $written[] = $redirection[1] !== '' ? $redirection[1] : ($tokens[++$i] ?? null);
                } else {
                    $words[] = $tokens[$i];
                }
            }

            $program = basename($words[0] ?? '');
            $options = array_filter($words, fn (string $word) => str_starts_with($word, '-'));
            $files = array_values(array_filter(array_slice($words, 1), fn (string $word) => ! str_starts_with($word, '-')));
            $inPlace = (bool) array_filter($options, fn (string $word) => preg_match('/^-[a-z]*i|^--in-place/', $word) === 1);

            $written = [...$written, ...match (true) {
                in_array($program, ['sed', 'perl'], true) && $inPlace, in_array($program, ['tee', 'rm', 'mv'], true) => $files,
                $program === 'cp' && $files !== [] => [end($files)],
                default => [],
            }];
        }

        return array_values(array_filter($written, fn (?string $path) => $path !== null && $path !== ''));
    }

    /**
     * The words of a shell command, with their quotes removed.
     *
     * @return list<string>
     */
    private function words(string $command): array
    {
        preg_match_all('/"[^"]*"|\'[^\']*\'|\S+/', $command, $matches);

        return array_map(fn (string $word) => trim($word, '"\''), $matches[0]);
    }

    /** The migration name of a PHP file in one of the migration paths, or null for any other file. */
    private function migration(string $path): ?string
    {
        if (! str_ends_with($path, '.php')) {
            return null;
        }

        foreach ($this->migrationPaths() as $folder) {
            if (str_starts_with($path, rtrim($folder, '/').'/')) {
                return basename($path, '.php');
            }
        }

        return null;
    }

    private function absolute(string $path, PreToolUseInput $input): string
    {
        return str_starts_with($path, '/') ? $path : rtrim($input->cwd() ?? base_path(), '/').'/'.$path;
    }
}
